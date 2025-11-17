<script lang="ts">
	import { onMount } from 'svelte';
	import { calculatorApi, type EmployeeSearchResult, type BatchCalculationItem, type BatchCalculationResponse, type CalculatorResponse, type CalculationHistory } from '$lib/api/calculator.js';
	import { componentsApi, type Component } from '$lib/api/components.js';
	import { deductionComponentsApi, type DeductionComponent } from '$lib/api/deductionComponents.js';
	import { toast } from '$lib/stores/toast.js';

	let loading = false;
	let loadingEmployees = false;
	let savingHistory = false;
	let loadingComponents = false;
	let loadingDeductionComponents = false;
	let searchQuery = '';
	let employees: EmployeeSearchResult[] = [];
	let components: Component[] = [];
	let deductionComponents: DeductionComponent[] = [];
	let pagination = {
		current_page: 1,
		last_page: 1,
		per_page: 15,
		total: 0
	};
	let selectedEmployeeIds: Set<number> = new Set();
	let selectedEmployees: Map<number, EmployeeSearchResult & { 
		calcData: BatchCalculationItem;
		earnings: Map<number, number>; // component_id -> amount
		deductions: Map<number, number>; // deduction_component_id -> amount
		preview?: CalculatorResponse;
		previewLoading?: boolean;
		formattedValues?: {
			bruto: string;
			biaya_jabatan: string;
			iuran_pensiun: string;
			zakat: string;
		};
		isManualInput?: {
			biaya_jabatan: boolean;
			iuran_pensiun: boolean;
		};
	}> = new Map();
	let batchResult: BatchCalculationResponse | null = null;
	let month = new Date().getMonth() + 1;
	let year = new Date().getFullYear();
	let calculationMode: 'monthly' | 'yearly' = 'monthly'; // Default: bulanan
	let previousCalculationMode: 'monthly' | 'yearly' | null = null;
	let currentStep: 1 | 2 | 3 = 1; // Wizard steps: 1=Pilih Pegawai, 2=Input Data, 3=Hasil
	
	// Modal warning untuk duplikasi
	let showDuplicateWarning = false;
	let duplicateWarningModal: HTMLDialogElement | null = null;
	let duplicateEmployees: Array<{ employment_id: number; person_name: string }> = [];

	const monthOptions = Array.from({ length: 12 }, (_, i) => ({
		value: i + 1,
		label: new Date(2024, i).toLocaleString('id-ID', { month: 'long' })
	}));

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('id-ID', {
			style: 'currency',
			currency: 'IDR',
			minimumFractionDigits: 0
		}).format(amount);
	}

	// Format number with thousand separators (Indonesian format: 9.000.000)
	function formatNumber(value: number | string | undefined | null): string {
		if (value === null || value === undefined || value === '') return '';
		const num = typeof value === 'string' ? parseFloat(value.replace(/\./g, '')) : value;
		if (isNaN(num) || num === 0) return '';
		return Math.floor(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	// Parse formatted number string to number
	function parseFormattedNumber(value: string): number {
		if (!value) return 0;
		// Remove all dots (thousand separators) and parse
		const cleaned = value.replace(/\./g, '').replace(/Rp\s?/gi, '').trim();
		const num = parseFloat(cleaned);
		return isNaN(num) ? 0 : Math.floor(num);
	}

function getMonthlyAnnualAmounts(
	value: number,
	mode: 'monthly' | 'yearly'
): { monthlyAmount: number; annualAmount: number } {
	const safeValue = Math.max(0, Math.round(value));
	if (mode === 'monthly') {
		return {
			monthlyAmount: safeValue,
			annualAmount: safeValue * 12
		};
	}

	return {
		monthlyAmount: Math.max(0, Math.round(safeValue / 12)),
		annualAmount: safeValue
	};
}

	// Handle input with auto-formatting
	function handleCurrencyInput(employmentId: number, field: keyof BatchCalculationItem | 'zakat', event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		let value = input.value;
		
		// Save cursor position
		const cursorPos = input.selectionStart || 0;
		
		// Remove all non-digit characters
		const digitsOnly = value.replace(/\D/g, '');
		
		// Parse to number
		const numValue = digitsOnly ? parseFormattedNumber(digitsOnly) : 0;
		
		// Format with thousand separators
		const formatted = digitsOnly ? formatNumber(digitsOnly) : '';
		
		// Update input display value immediately (real-time)
		input.value = formatted;
		
		// Calculate new cursor position (adjust for added dots)
		const digitsBeforeCursor = value.substring(0, cursorPos).replace(/\D/g, '').length;
		const newDotsBeforeCursor = Math.floor((digitsBeforeCursor - 1) / 3);
		const newCursorPos = Math.max(0, Math.min(formatted.length, digitsBeforeCursor + newDotsBeforeCursor));
		input.setSelectionRange(newCursorPos, newCursorPos);
		
		// Update formatted value in state
		const employee = selectedEmployees.get(employmentId);
		if (employee) {
			if (!employee.formattedValues) {
				employee.formattedValues = {
					bruto: '',
					biaya_jabatan: '',
					iuran_pensiun: '',
					zakat: ''
				};
			}
			employee.formattedValues[field as keyof typeof employee.formattedValues] = formatted;
		}
		
		// Handle zakat field
		if (field === 'zakat') {
			if (employee) {
				// Set to 0 if empty, otherwise use the numeric value
				employee.calcData.zakat = digitsOnly ? numValue : 0;
				selectedEmployees.set(employmentId, employee);
				selectedEmployees = new Map(selectedEmployees);
				
				// Preview dengan debounce (hanya jika bruto > 0)
				if (employee.calcData.bruto > 0) {
					debouncedPreview(employmentId);
				}
			}
			return;
		}
		
		// Update the data model
		if (field === 'biaya_jabatan' || field === 'iuran_pensiun') {
			// Jika user menghapus nilai (kosong), set ke undefined untuk auto-calculate
			const valueToSet = digitsOnly ? numValue : undefined;
			
			// Update langsung
			if (employee) {
				// Ensure isManualInput exists
				if (!employee.isManualInput) {
					employee.isManualInput = {
						biaya_jabatan: false,
						iuran_pensiun: false
					};
				}
				
				// Mark as manual input jika user isi nilai
				if (digitsOnly) {
					employee.isManualInput[field] = true;
				} else {
					// Reset manual flag jika dihapus
					employee.isManualInput[field] = false;
				}
				
				employee.calcData[field] = valueToSet;
				
				// Jika dihapus, trigger auto-calculate dari bruto
				if (!digitsOnly) {
					const bruto = employee.calcData.bruto || 0;
					if (bruto > 0) {
						// Ensure formattedValues exists
						if (!employee.formattedValues) {
							employee.formattedValues = {
								bruto: '',
								biaya_jabatan: '',
								iuran_pensiun: '',
								zakat: ''
							};
						}
						
						// Re-calculate auto values with appropriate limits based on mode
						if (field === 'biaya_jabatan') {
							const biayaJabatanLimit = calculationMode === 'monthly' ? 500000 : 6000000;
							const biayaJabatan = Math.min(bruto * 0.05, biayaJabatanLimit);
							employee.calcData.biaya_jabatan = biayaJabatan;
							employee.formattedValues.biaya_jabatan = formatNumber(biayaJabatan);
						} else if (field === 'iuran_pensiun') {
							const iuranPensiunLimit = calculationMode === 'monthly' ? 200000 : 2400000;
							const iuranPensiun = Math.min(bruto * 0.05, iuranPensiunLimit);
							employee.calcData.iuran_pensiun = iuranPensiun;
							employee.formattedValues.iuran_pensiun = formatNumber(iuranPensiun);
						}
					}
				}
				
				selectedEmployees.set(employmentId, employee);
				selectedEmployees = new Map(selectedEmployees);
				
				// Preview dengan debounce (hanya jika bruto > 0)
				if (employee.calcData.bruto > 0) {
					debouncedPreview(employmentId);
				}
			}
		} else {
			// Untuk field lain (biaya_jabatan, iuran_pensiun, zakat), update langsung
			const employee = selectedEmployees.get(employmentId);
			if (employee) {
				employee.calcData[field] = numValue;
				if (employee.formattedValues) {
					employee.formattedValues[field as keyof typeof employee.formattedValues] = formatted;
				}
				selectedEmployees = new Map(selectedEmployees);
				if (numValue > 0) {
					debouncedPreview(employmentId);
				}
			}
		}
	}

	// Get formatted display value for input
	function getFormattedInputValue(employmentId: number, field: keyof BatchCalculationItem): string {
		const employee = selectedEmployees.get(employmentId);
		if (!employee) return '';
		
		// Untuk biaya_jabatan dan iuran_pensiun, selalu ambil dari calcData jika ada
		if (field === 'biaya_jabatan' || field === 'iuran_pensiun') {
			const value = employee.calcData[field];
			if (value !== undefined && value !== null && value > 0) {
				const formatted = formatNumber(value);
				// Update formattedValues untuk konsistensi
				if (!employee.formattedValues) {
					employee.formattedValues = {
						bruto: '',
						biaya_jabatan: '',
						iuran_pensiun: '',
						zakat: ''
					};
				}
				employee.formattedValues[field as keyof typeof employee.formattedValues] = formatted;
				return formatted;
			}
			return '';
		}
		
		// Untuk field lain, gunakan formattedValues jika ada
		if (employee.formattedValues) {
			const formatted = employee.formattedValues[field as keyof typeof employee.formattedValues];
			if (formatted !== undefined && formatted !== '') {
				return formatted;
			}
		}
		
		// Fallback ke calcData
		const value = employee.calcData[field];
		if (value === null || value === undefined || value === 0) return '';
		const formatted = formatNumber(value);
		// Update formattedValues for consistency
		if (!employee.formattedValues) {
			employee.formattedValues = {
				bruto: '',
				biaya_jabatan: '',
				iuran_pensiun: '',
				zakat: ''
			};
		}
		employee.formattedValues[field as keyof typeof employee.formattedValues] = formatted;
		return formatted;
	}

	async function loadEmployees(page = 1) {
		loadingEmployees = true;
		try {
			const response = await calculatorApi.searchEmployees({
				search: searchQuery.trim() || undefined,
				per_page: pagination.per_page,
				page
			});
			employees = response.data || [];
			pagination = {
				current_page: response.current_page || page,
				last_page: response.last_page || 1,
				per_page: response.per_page || pagination.per_page,
				total: response.total || 0
			};
		} catch (error: any) {
			console.error('Load employees error:', error);
			toast.error('Gagal memuat daftar pegawai');
			employees = [];
		} finally {
			loadingEmployees = false;
		}
	}

	async function loadComponents() {
		loadingComponents = true;
		try {
			const response = await componentsApi.list({ 
				per_page: 1000 
			});
			// Filter only active components
			components = (response.data || []).filter(c => c.is_active !== false);
			// Sort by priority (smaller number = higher priority)
			components.sort((a, b) => (a.priority || 0) - (b.priority || 0));
		} catch (error: any) {
			console.error('Failed to load components:', error);
			toast.error('Gagal memuat daftar komponen earning');
			components = [];
		} finally {
			loadingComponents = false;
		}
	}

	async function loadDeductionComponents() {
		loadingDeductionComponents = true;
		try {
			const response = await deductionComponentsApi.list({ 
				is_active: true,
				per_page: 1000 
			});
			deductionComponents = response.data || [];
			// Sort by priority (smaller number = higher priority)
			deductionComponents.sort((a, b) => (a.priority || 0) - (b.priority || 0));
		} catch (error: any) {
			console.error('Failed to load deduction components:', error);
			toast.error('Gagal memuat daftar komponen deduction');
			deductionComponents = [];
		} finally {
			loadingDeductionComponents = false;
		}
	}

	function toggleEmployeeSelection(employee: EmployeeSearchResult) {
		if (selectedEmployeeIds.has(employee.id)) {
			selectedEmployeeIds.delete(employee.id);
			selectedEmployees.delete(employee.id);
		} else {
			selectedEmployeeIds.add(employee.id);
			selectedEmployees.set(employee.id, {
				...employee,
				calcData: {
					employment_id: employee.id,
					bruto: 0,
					biaya_jabatan: undefined,
					iuran_pensiun: undefined,
					zakat: 0
				},
				earnings: new Map<number, number>(), // component_id -> amount
				deductions: new Map<number, number>(), // deduction_component_id -> amount
				formattedValues: {
					bruto: '',
					biaya_jabatan: '',
					iuran_pensiun: '',
					zakat: ''
				},
				isManualInput: {
					biaya_jabatan: false,
					iuran_pensiun: false
				}
			});
		}
		// Force reactivity by reassigning
		selectedEmployeeIds = new Set(selectedEmployeeIds);
		selectedEmployees = new Map(selectedEmployees);
	}

	function removeEmployee(employmentId: number) {
		selectedEmployeeIds.delete(employmentId);
		selectedEmployees.delete(employmentId);
		if (selectedEmployees.size === 0) {
			currentStep = 1;
		}
	}

	// Calculate total bruto from taxable earnings
	function calculateBruto(employmentId: number): number {
		const employee = selectedEmployees.get(employmentId);
		if (!employee || !employee.earnings) return 0;
		
		let total = 0;
		employee.earnings.forEach((amount, componentId) => {
			const component = components.find(c => c.id === componentId);
			if (component && component.taxable) {
				total += amount || 0;
			}
		});
		return total;
	}

	// Handle earning input per component
	function handleEarningInput(employmentId: number, componentId: number, event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		let value = input.value;
		
		// Save cursor position
		const cursorPos = input.selectionStart || 0;
		
		// Remove all non-digit characters
		const digitsOnly = value.replace(/\D/g, '');
		
		// Parse to number
		const numValue = digitsOnly ? parseFormattedNumber(digitsOnly) : 0;
		
		// Format with thousand separators
		const formatted = digitsOnly ? formatNumber(digitsOnly) : '';
		
		// Update input display value immediately (real-time)
		input.value = formatted;
		
		// Calculate new cursor position (adjust for added dots)
		const digitsBeforeCursor = value.substring(0, cursorPos).replace(/\D/g, '').length;
		const newDotsBeforeCursor = Math.floor((digitsBeforeCursor - 1) / 3);
		const newCursorPos = Math.max(0, Math.min(formatted.length, digitsBeforeCursor + newDotsBeforeCursor));
		input.setSelectionRange(newCursorPos, newCursorPos);
		
		// Update earnings map
		const employee = selectedEmployees.get(employmentId);
		if (employee) {
			if (!employee.earnings) {
				employee.earnings = new Map<number, number>();
			}
			if (numValue > 0) {
				employee.earnings.set(componentId, numValue);
			} else {
				employee.earnings.delete(componentId);
			}
			
			// Update bruto automatically from taxable earnings
			const bruto = calculateBruto(employmentId);
			employee.calcData.bruto = bruto;
			
			// Update formatted bruto
			if (!employee.formattedValues) {
				employee.formattedValues = {
					bruto: '',
					biaya_jabatan: '',
					iuran_pensiun: '',
					zakat: ''
				};
			}
			employee.formattedValues.bruto = formatNumber(bruto);
			
			// Force reactivity
			selectedEmployees = new Map(selectedEmployees);
			
			// Auto-calculate deduction components dengan calculation_type === 'auto' ATAU iuran_pensiun (selalu auto)
			if (bruto > 0) {
				// Ensure isManualInput exists
				if (!employee.isManualInput) {
					employee.isManualInput = {
						biaya_jabatan: false,
						iuran_pensiun: false
					};
				}
				
				// Loop through deduction components dengan calculation_type === 'auto' ATAU iuran_pensiun (selalu auto)
				deductionComponents
					.filter(dc => dc.calculation_type === 'auto' || isIuranPensiun(dc))
					.forEach(deductionComponent => {
						let calculatedAmount = 0;
						
						// Calculate based on component type
						if (isBiayaJabatan(deductionComponent)) {
							// Biaya Jabatan: 5% dari bruto, max 500rb/bulan atau 6jt/tahun
							const limit = calculationMode === 'monthly' ? 500000 : 6000000;
							calculatedAmount = Math.min(bruto * 0.05, limit);
							
							// Update calcData untuk backward compatibility
							if (employee.isManualInput && !employee.isManualInput.biaya_jabatan) {
								employee.calcData.biaya_jabatan = calculatedAmount;
								if (employee.formattedValues) {
									employee.formattedValues.biaya_jabatan = formatNumber(calculatedAmount);
								}
							}
						} else if (isIuranPensiun(deductionComponent)) {
							// Iuran Pensiun: 5% dari bruto, max 200rb/bulan atau 2.4jt/tahun
							const limit = calculationMode === 'monthly' ? 200000 : 2400000;
							calculatedAmount = Math.min(bruto * 0.05, limit);
							
							// Update calcData untuk backward compatibility
							if (employee.isManualInput && !employee.isManualInput.iuran_pensiun) {
								employee.calcData.iuran_pensiun = calculatedAmount;
								if (employee.formattedValues) {
									employee.formattedValues.iuran_pensiun = formatNumber(calculatedAmount);
								}
							}
						} else {
							// Komponen auto lainnya (jika ada) - bisa dikembangkan sesuai kebutuhan
							// Untuk sekarang, skip komponen auto selain biaya_jabatan dan iuran_pensiun
						}
						
						// Update deductions map
						if (!employee.deductions) {
							employee.deductions = new Map<number, number>();
						}
						if (calculatedAmount > 0) {
							employee.deductions.set(deductionComponent.id, calculatedAmount);
						}
					});
			} else {
				// Reset jika bruto = 0 (hanya jika bukan manual input)
				if (!employee.isManualInput) {
					employee.isManualInput = {
						biaya_jabatan: false,
						iuran_pensiun: false
					};
				}
				
				// Reset auto-calculated deductions (termasuk iuran_pensiun yang selalu auto)
				deductionComponents
					.filter(dc => dc.calculation_type === 'auto' || isIuranPensiun(dc))
					.forEach(deductionComponent => {
						if (isBiayaJabatan(deductionComponent) && employee.isManualInput && !employee.isManualInput.biaya_jabatan) {
							employee.calcData.biaya_jabatan = undefined;
							if (employee.formattedValues) {
								employee.formattedValues.biaya_jabatan = '';
							}
							if (employee.deductions) {
								employee.deductions.delete(deductionComponent.id);
							}
						} else if (isIuranPensiun(deductionComponent) && employee.isManualInput && !employee.isManualInput.iuran_pensiun) {
							employee.calcData.iuran_pensiun = undefined;
							if (employee.formattedValues) {
								employee.formattedValues.iuran_pensiun = '';
							}
							if (employee.deductions) {
								employee.deductions.delete(deductionComponent.id);
							}
						}
					});
			}
			
			// Trigger preview dengan debounce
			if (bruto > 0) {
				debouncedPreview(employmentId);
			}
		}
	}

	// Get earning amount for a specific component
	function getEarningAmount(employmentId: number, componentId: number): string {
		const employee = selectedEmployees.get(employmentId);
		if (!employee || !employee.earnings) return '';
		const amount = employee.earnings.get(componentId) || 0;
		return amount > 0 ? formatNumber(amount) : '';
	}

	// Helper: Check if deduction component is biaya_jabatan
	function isBiayaJabatan(component: DeductionComponent): boolean {
		return component.code === 'biaya_jabatan' || 
		       (component.calculation_type === 'auto' && 
		        component.name.toLowerCase().includes('biaya jabatan'));
	}

	// Helper: Check if deduction component is iuran_pensiun
	// Iuran Pensiun SELALU auto-calculate sesuai aturan: 5% dari bruto, max 200rb/bulan atau 2.4jt/tahun
	function isIuranPensiun(component: DeductionComponent): boolean {
		return component.code === 'iuran_pensiun' || 
		       component.name.toLowerCase().includes('iuran pensiun');
	}

	// Helper: Check if deduction component is zakat
	function isZakat(component: DeductionComponent): boolean {
		return component.code === 'zakat' || 
		       component.name.toLowerCase().includes('zakat');
	}

	// Handle deduction input per component
	function handleDeductionInput(employmentId: number, deductionComponentId: number, event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		let value = input.value;
		
		// Save cursor position
		const cursorPos = input.selectionStart || 0;
		
		// Remove all non-digit characters
		const digitsOnly = value.replace(/\D/g, '');
		
		// Parse to number
		const numValue = digitsOnly ? parseFormattedNumber(digitsOnly) : 0;
		
		// Format with thousand separators
		const formatted = digitsOnly ? formatNumber(digitsOnly) : '';
		
		// Update input display value immediately (real-time)
		input.value = formatted;
		
		// Calculate new cursor position (adjust for added dots)
		const digitsBeforeCursor = value.substring(0, cursorPos).replace(/\D/g, '').length;
		const newDotsBeforeCursor = Math.floor((digitsBeforeCursor - 1) / 3);
		const newCursorPos = Math.max(0, Math.min(formatted.length, digitsBeforeCursor + newDotsBeforeCursor));
		input.setSelectionRange(newCursorPos, newCursorPos);
		
		// Update deductions map
		const employee = selectedEmployees.get(employmentId);
		const deductionComponent = deductionComponents.find(dc => dc.id === deductionComponentId);
		
		if (employee && deductionComponent) {
			if (!employee.deductions) {
				employee.deductions = new Map<number, number>();
			}
			if (numValue > 0) {
				employee.deductions.set(deductionComponentId, numValue);
			} else {
				employee.deductions.delete(deductionComponentId);
			}
			
			// Update calcData untuk komponen khusus (biaya_jabatan, iuran_pensiun, zakat)
			if (isBiayaJabatan(deductionComponent)) {
				employee.calcData.biaya_jabatan = numValue > 0 ? numValue : undefined;
				if (!employee.isManualInput) {
					employee.isManualInput = { biaya_jabatan: false, iuran_pensiun: false };
				}
				employee.isManualInput.biaya_jabatan = numValue > 0;
				if (employee.formattedValues) {
					employee.formattedValues.biaya_jabatan = formatted;
				}
			} else if (isIuranPensiun(deductionComponent)) {
				employee.calcData.iuran_pensiun = numValue > 0 ? numValue : undefined;
				if (!employee.isManualInput) {
					employee.isManualInput = { biaya_jabatan: false, iuran_pensiun: false };
				}
				employee.isManualInput.iuran_pensiun = numValue > 0;
				if (employee.formattedValues) {
					employee.formattedValues.iuran_pensiun = formatted;
				}
			} else if (isZakat(deductionComponent)) {
				employee.calcData.zakat = numValue;
				if (employee.formattedValues) {
					employee.formattedValues.zakat = formatted;
				}
			}
			
			// Force reactivity
			selectedEmployees = new Map(selectedEmployees);
			
			// Trigger preview dengan debounce
			if (employee.calcData.bruto > 0) {
				debouncedPreview(employmentId);
			}
		}
	}

	// Get deduction amount for a specific component
	function getDeductionAmount(employmentId: number, deductionComponentId: number): string {
		const employee = selectedEmployees.get(employmentId);
		if (!employee || !employee.deductions) return '';
		const amount = employee.deductions.get(deductionComponentId) || 0;
		return amount > 0 ? formatNumber(amount) : '';
	}

	// Get deduction amount from calcData (for backward compatibility with biaya_jabatan, iuran_pensiun, zakat)
	function getDeductionAmountFromCalcData(employmentId: number, deductionComponent: DeductionComponent): string {
		const employee = selectedEmployees.get(employmentId);
		if (!employee) return '';
		
		if (isBiayaJabatan(deductionComponent)) {
			return employee.formattedValues?.biaya_jabatan || '';
		} else if (isIuranPensiun(deductionComponent)) {
			return employee.formattedValues?.iuran_pensiun || '';
		} else if (isZakat(deductionComponent)) {
			return employee.formattedValues?.zakat || '';
		}
		
		// Fallback to deductions map
		return getDeductionAmount(employmentId, deductionComponent.id);
	}

	let previewTimeouts: Map<number, ReturnType<typeof setTimeout>> = new Map();
	function debouncedPreview(employmentId: number) {
		// Clear existing timeout for this employee
		const existing = previewTimeouts.get(employmentId);
		if (existing) clearTimeout(existing);
		
		// Set new timeout dengan delay lebih lama untuk mengurangi beban server
		// Preview hanya dipanggil saat user berhenti mengetik
		const timeout = setTimeout(() => {
			previewCalculation(employmentId);
			previewTimeouts.delete(employmentId);
		}, 800);
		
		previewTimeouts.set(employmentId, timeout);
	}

	async function previewCalculation(employmentId: number) {
		const employee = selectedEmployees.get(employmentId);
		if (!employee || employee.calcData.bruto <= 0) {
			if (employee) {
				employee.preview = undefined;
			}
			return;
		}

		employee.previewLoading = true;
		try {
			// Ensure zakat is properly sent as a number (0 if empty/null/undefined)
			// Handle null, undefined, or 0 - all should be treated as 0
			let zakatValue: number = employee.calcData.zakat ?? 0;
			// Ensure it's a valid number (not NaN, not negative)
			if (!zakatValue || isNaN(zakatValue) || zakatValue < 0) {
				zakatValue = 0;
			}
			
			// Calculate bruto dari taxable earnings (menggunakan format baru)
			let annualBruto = calculateBruto(employmentId);
			if (calculationMode === 'monthly') {
				annualBruto = annualBruto * 12;
			}
			
			// Get biaya_jabatan dan iuran_pensiun dari calcData (untuk backward compatibility)
			let annualBiayaJabatan = employee.calcData.biaya_jabatan;
			let annualIuranPensiun = employee.calcData.iuran_pensiun;
			
			if (calculationMode === 'monthly') {
				// If monthly mode, multiply by 12 for annual
				if (annualBiayaJabatan !== undefined) {
					annualBiayaJabatan = annualBiayaJabatan * 12;
				}
				if (annualIuranPensiun !== undefined) {
					annualIuranPensiun = annualIuranPensiun * 12;
				}
			}
			
			// Get zakat dari calcData atau deductions map
			let annualZakat = employee.calcData.zakat ?? 0;
			if (annualZakat === 0 && employee.deductions) {
				// Cari zakat dari deductions map
				const zakatComponent = deductionComponents.find(dc => isZakat(dc));
				if (zakatComponent) {
					annualZakat = employee.deductions.get(zakatComponent.id) || 0;
				}
			}
			if (calculationMode === 'monthly') {
				annualZakat = annualZakat * 12;
			}
			if (!annualZakat || isNaN(annualZakat) || annualZakat < 0) {
				annualZakat = 0;
			}
			
			// Build earnings array (untuk format baru - siap untuk backend update)
			const earningsArray: Array<{ component_id: number; amount: number }> = [];
			if (employee.earnings) {
				employee.earnings.forEach((amount, componentId) => {
					if (amount > 0) {
						let annualAmount = amount;
						if (calculationMode === 'monthly') {
							annualAmount = amount * 12;
						}
						earningsArray.push({
							component_id: componentId,
							amount: annualAmount
						});
					}
				});
			}
			
			// Build deductions array (untuk format baru - siap untuk backend update)
			const deductionsArray: Array<{ deduction_component_id: number; amount: number }> = [];
			if (employee.deductions) {
				employee.deductions.forEach((amount, deductionComponentId) => {
					if (amount > 0) {
						let annualAmount = amount;
						if (calculationMode === 'monthly') {
							annualAmount = amount * 12;
						}
						deductionsArray.push({
							deduction_component_id: deductionComponentId,
							amount: annualAmount
						});
					}
				});
			}
			
			// Prepare request payload - menggunakan format baru (earnings, deductions)
			const requestPayload: any = {
				ptkp_code: employee.ptkp_code,
				earnings: earningsArray,
				deductions: deductionsArray,
				month: month,
				has_npwp: employee.has_npwp
			};
			
			const result = await calculatorApi.calculatePPh21(requestPayload);
			employee.preview = result;
		} catch (error: any) {
			console.error('Preview error:', error);
			employee.preview = undefined;
		} finally {
			employee.previewLoading = false;
		}
	}

	// Cek apakah ada perhitungan yang sudah ada untuk pegawai yang dipilih
	async function checkDuplicateCalculations(): Promise<Array<{ employment_id: number; person_name: string }>> {
		const duplicates: Array<{ employment_id: number; person_name: string }> = [];
		
		// Cek untuk setiap pegawai yang dipilih
		for (const employee of selectedEmployees.values()) {
			try {
				const history = await calculatorApi.getHistory({
					employment_id: employee.id,
					year: year,
					month: month,
					per_page: 1
				});
				
				// Jika ada history untuk bulan/tahun ini, berarti duplikasi
				if (history.data && history.data.length > 0) {
					duplicates.push({
						employment_id: employee.id,
						person_name: employee.person_name
					});
				}
			} catch (error) {
				// Jika error saat cek, skip pegawai ini (biarkan tetap dihitung)
				console.error('Error checking duplicate for employee:', employee.id, error);
			}
		}
		
		return duplicates;
	}

	// Fungsi untuk melakukan perhitungan (dipanggil setelah konfirmasi)
	async function performCalculation() {
		if (selectedEmployees.size === 0) {
			batchResult = null;
			return;
		}

		// Check if at least one employee has bruto > 0
		const hasValidData = Array.from(selectedEmployees.values()).some(
			(emp) => emp.calcData.bruto > 0
		);

		if (!hasValidData) {
			batchResult = null;
			return;
		}

		loading = true;
		batchResult = null;

		try {
			// Convert calculations based on mode - menggunakan format baru dengan earnings dan deductions array
			const calculations: BatchCalculationItem[] = Array.from(selectedEmployees.values()).map(
				(emp) => {
					// Build earnings array dari komponen earning
					const earningsArray: Array<{ component_id: number; amount: number }> = [];
					if (emp.earnings) {
						emp.earnings.forEach((amount, componentId) => {
							if (amount > 0) {
								// Convert to annual if monthly mode
								let annualAmount = amount;
								if (calculationMode === 'monthly') {
									annualAmount = amount * 12;
								}
								earningsArray.push({
									component_id: componentId,
									amount: annualAmount
								});
							}
						});
					}
					
					// Build deductions array dari komponen deduction
					const deductionsArray: Array<{ deduction_component_id: number; amount: number }> = [];
					if (emp.deductions) {
						emp.deductions.forEach((amount, deductionComponentId) => {
							if (amount > 0) {
								// Convert to annual if monthly mode
								let annualAmount = amount;
								if (calculationMode === 'monthly') {
									annualAmount = amount * 12;
								}
								deductionsArray.push({
									deduction_component_id: deductionComponentId,
									amount: annualAmount
								});
							}
						});
					}
					
					// Calculate bruto dari taxable earnings (untuk backward compatibility dengan backend)
					let bruto = calculateBruto(emp.id);
					if (calculationMode === 'monthly') {
						bruto = bruto * 12;
					}
					
					// Get biaya_jabatan dan iuran_pensiun dari calcData (untuk backward compatibility)
					let biayaJabatan = emp.calcData.biaya_jabatan;
					let iuranPensiun = emp.calcData.iuran_pensiun;
					if (calculationMode === 'monthly') {
						if (biayaJabatan !== undefined) {
							biayaJabatan = biayaJabatan * 12;
						}
						if (iuranPensiun !== undefined) {
							iuranPensiun = iuranPensiun * 12;
						}
					}
					
					// Get zakat dari calcData atau deductions
					let zakat: number = emp.calcData.zakat ?? 0;
					if (zakat === 0 && emp.deductions) {
						// Cari zakat dari deductions map
						const zakatComponent = deductionComponents.find(dc => isZakat(dc));
						if (zakatComponent) {
							const zakatAmount = emp.deductions.get(zakatComponent.id) || 0;
							zakat = zakatAmount;
						}
					}
					if (calculationMode === 'monthly') {
						zakat = zakat * 12;
					}
					if (!zakat || isNaN(zakat) || zakat < 0) {
						zakat = 0;
					}
					
					// Build calculation item - menggunakan format baru (earnings, deductions)
					// @ts-ignore - temporary untuk format baru
					const calcItem: any = {
						employment_id: emp.calcData.employment_id,
						earnings: earningsArray,
						deductions: deductionsArray
					};
					
					return calcItem;
				}
			);

			batchResult = await calculatorApi.calculateBatch({
				calculations,
				month
			});
			
			// Switch to results step
			currentStep = 3;
		} catch (error: any) {
			console.error('Calculation error:', error);
			toast.error(error.message || 'Gagal menghitung PPh 21');
		} finally {
			loading = false;
		}
	}

	// Fungsi utama yang dipanggil saat tombol "Hitung PPh 21" diklik
	async function calculateBatch() {
		if (selectedEmployees.size === 0) {
			batchResult = null;
			return;
		}

		// Check if at least one employee has bruto > 0
		const hasValidData = Array.from(selectedEmployees.values()).some(
			(emp) => emp.calcData.bruto > 0
		);

		if (!hasValidData) {
			batchResult = null;
			return;
		}

		// Cek duplikasi sebelum melakukan perhitungan
		loading = true;
		try {
			const duplicates = await checkDuplicateCalculations();
			
			if (duplicates.length > 0) {
				// Ada duplikasi, tampilkan modal warning
				duplicateEmployees = duplicates;
				showDuplicateWarning = true;
				if (duplicateWarningModal) {
					duplicateWarningModal.showModal();
				}
			} else {
				// Tidak ada duplikasi, langsung hitung
				await performCalculation();
			}
		} catch (error: any) {
			console.error('Error checking duplicates:', error);
			// Jika error saat cek, tetap lanjutkan perhitungan
			await performCalculation();
		} finally {
			loading = false;
		}
	}

	// Fungsi untuk handle konfirmasi dari modal
	async function handleConfirmCalculation() {
		showDuplicateWarning = false;
		if (duplicateWarningModal) {
			duplicateWarningModal.close();
		}
		duplicateEmployees = [];
		
		// Lakukan perhitungan
		await performCalculation();
	}

	// Fungsi untuk handle cancel dari modal
	function handleCancelCalculation() {
		showDuplicateWarning = false;
		if (duplicateWarningModal) {
			duplicateWarningModal.close();
		}
		duplicateEmployees = [];
	}

	function getEmployeeResult(employmentId: number) {
		if (!batchResult) return null;
		return batchResult.results.find((r) => r.employment_id === employmentId);
	}

function buildEarningsBreakdownPayload(
	employmentId: number,
	mode: 'monthly' | 'yearly'
) {
	const employee = selectedEmployees.get(employmentId);
	if (!employee || !employee.earnings || employee.earnings.size === 0) {
		return [];
	}

	const breakdown: Array<{
		component_id: number;
		monthly_amount: number;
		annual_amount: number;
	}> = [];

	employee.earnings.forEach((amount, componentId) => {
		const numericAmount = Number(amount) || 0;
		if (numericAmount <= 0) {
			return;
		}
		const { monthlyAmount, annualAmount } = getMonthlyAnnualAmounts(numericAmount, mode);
		breakdown.push({
			component_id: componentId,
			monthly_amount: monthlyAmount,
			annual_amount: annualAmount
		});
	});

	return breakdown;
}

function buildDeductionsBreakdownPayload(
	employmentId: number,
	mode: 'monthly' | 'yearly'
) {
	const employee = selectedEmployees.get(employmentId);
	if (!employee) {
		return [];
	}

	const deductionMap = new Map<number, number>();

	if (employee.deductions) {
		employee.deductions.forEach((amount, deductionId) => {
			if ((Number(amount) || 0) > 0) {
				deductionMap.set(deductionId, Number(amount));
			}
		});
	}

	// Ensure special components are captured even if not in the map
	const biayaJabatanComponent = deductionComponents.find((dc) => isBiayaJabatan(dc));
	if (
		biayaJabatanComponent &&
		employee.calcData.biaya_jabatan &&
		!deductionMap.has(biayaJabatanComponent.id)
	) {
		deductionMap.set(biayaJabatanComponent.id, employee.calcData.biaya_jabatan);
	}

	const iuranPensiunComponent = deductionComponents.find((dc) => isIuranPensiun(dc));
	if (
		iuranPensiunComponent &&
		employee.calcData.iuran_pensiun &&
		!deductionMap.has(iuranPensiunComponent.id)
	) {
		deductionMap.set(iuranPensiunComponent.id, employee.calcData.iuran_pensiun);
	}

	const zakatComponent = deductionComponents.find((dc) => isZakat(dc));
	if (
		zakatComponent &&
		employee.calcData.zakat &&
		!deductionMap.has(zakatComponent.id)
	) {
		deductionMap.set(zakatComponent.id, employee.calcData.zakat);
	}

	if (deductionMap.size === 0) {
		return [];
	}

	const breakdown: Array<{
		deduction_component_id: number;
		monthly_amount: number;
		annual_amount: number;
	}> = [];

	deductionMap.forEach((amount, deductionId) => {
		const numericAmount = Number(amount) || 0;
		if (numericAmount <= 0) {
			return;
		}
		const { monthlyAmount, annualAmount } = getMonthlyAnnualAmounts(numericAmount, mode);
		breakdown.push({
			deduction_component_id: deductionId,
			monthly_amount: monthlyAmount,
			annual_amount: annualAmount
		});
	});

	return breakdown;
}

	async function saveToHistory() {
		if (selectedEmployees.size === 0 || !batchResult) {
			toast.error('Tidak ada perhitungan untuk disimpan');
			return;
		}

		savingHistory = true;
		try {
			const calculations = batchResult.results.map(result => {
				const earningsBreakdown = buildEarningsBreakdownPayload(result.employment_id, calculationMode);
				const deductionsBreakdown = buildDeductionsBreakdownPayload(result.employment_id, calculationMode);

				return {
					employment_id: result.employment_id,
					person_name: result.person_name,
					ptkp_code: result.ptkp_code,
					has_npwp: result.has_npwp,
					year: year,
					month: month,
					calculation_mode: calculationMode,
					bruto: result.bruto,
					biaya_jabatan: result.biaya_jabatan,
					iuran_pensiun: result.iuran_pensiun,
					zakat: result.zakat,
					neto_masa: result.neto_masa,
					ptkp_yearly: result.ptkp_yearly,
					pkp_annualized: result.pkp_annualized,
					pph21_masa: result.pph21_masa,
					notes: result.notes || null,
					earnings_breakdown: earningsBreakdown.length > 0 ? earningsBreakdown : undefined,
					deductions_breakdown: deductionsBreakdown.length > 0 ? deductionsBreakdown : undefined
				};
			});

			const response = await calculatorApi.saveHistory({ calculations });
			
			// Ensure toast is called even if response structure is unexpected
			const savedCount = response?.saved_count ?? calculations.length;
			const message = `Data berhasil disimpan! (${savedCount} perhitungan)`;
			toast.success(message);
		} catch (error: any) {
			console.error('Save history error:', error);
			const errorMessage = error?.response?.data?.message || error?.message || 'Gagal menyimpan data';
			toast.error(errorMessage);
		} finally {
			savingHistory = false;
		}
	}


	// Debounced search
	let searchTimeout: ReturnType<typeof setTimeout> | null = null;
	$: if (searchQuery !== null && searchQuery !== undefined) {
		if (searchTimeout) clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => {
			if (currentStep === 1) {
				loadEmployees(1);
			}
		}, 500);
	}

	// Re-calculate auto values when mode changes
	$: if (calculationMode && previousCalculationMode !== null && calculationMode !== previousCalculationMode) {
		// Only re-calculate when mode actually changes (not on initial mount)
		selectedEmployees.forEach((employee, id) => {
			if (employee.calcData.bruto > 0) {
				// Re-calculate biaya_jabatan if not manual
				if (!employee.isManualInput?.biaya_jabatan) {
					const biayaJabatanLimit = calculationMode === 'monthly' ? 500000 : 6000000;
					const biayaJabatan = Math.min(employee.calcData.bruto * 0.05, biayaJabatanLimit);
					employee.calcData.biaya_jabatan = biayaJabatan;
					if (employee.formattedValues) {
						employee.formattedValues.biaya_jabatan = formatNumber(biayaJabatan);
					}
				}
				
				// Re-calculate iuran_pensiun if not manual
				if (!employee.isManualInput?.iuran_pensiun) {
					const iuranPensiunLimit = calculationMode === 'monthly' ? 200000 : 2400000;
					const iuranPensiun = Math.min(employee.calcData.bruto * 0.05, iuranPensiunLimit);
					employee.calcData.iuran_pensiun = iuranPensiun;
					if (employee.formattedValues) {
						employee.formattedValues.iuran_pensiun = formatNumber(iuranPensiun);
					}
				}
				
				// Trigger preview update
				if (employee.calcData.bruto > 0) {
					debouncedPreview(id);
				}
			}
		});
		selectedEmployees = new Map(selectedEmployees);
		previousCalculationMode = calculationMode;
	} else if (previousCalculationMode === null) {
		// Set initial value
		previousCalculationMode = calculationMode;
	}

	onMount(async () => {
		await Promise.all([
			loadEmployees(),
			loadComponents(),
			loadDeductionComponents()
		]);
	});
</script>

<div class="space-y-6">
	<div class="flex justify-between items-center">
		<div>
			<h1 class="text-3xl font-bold text-base-content">Kalkulator PPh 21</h1>
			<p class="text-base-content opacity-70 mt-1">Hitung PPh 21 untuk satu atau beberapa pegawai sekaligus</p>
		</div>
		<div class="flex gap-2 items-center">
			{#if selectedEmployees.size > 0}
				<span class="badge badge-primary badge-lg">{selectedEmployees.size} pegawai terpilih</span>
			{/if}
			{#if currentStep > 1 || selectedEmployees.size > 0}
				<button class="btn btn-sm btn-ghost" on:click={() => {
					selectedEmployeeIds.clear();
					selectedEmployees.clear();
					batchResult = null;
					currentStep = 1;
				}}>
					Hitung Lagi
				</button>
			{/if}
		</div>
	</div>

	<!-- Wizard Progress Indicator -->
	<div class="card bg-base-100 shadow-lg">
		<div class="card-body py-4">
			<ul class="steps steps-horizontal w-full">
				<li class={`step ${currentStep >= 1 ? 'step-primary' : ''}`}>
					<div class="flex flex-col items-center">
						<span class="font-semibold text-base-content">Pilih Pegawai</span>
						{#if currentStep === 1 && selectedEmployees.size > 0}
							<span class="text-xs text-base-content opacity-70">{selectedEmployees.size} terpilih</span>
						{/if}
					</div>
				</li>
				<li class={`step ${currentStep >= 2 ? 'step-primary' : ''} ${selectedEmployees.size === 0 ? 'opacity-50' : ''}`}>
					<div class="flex flex-col items-center">
						<span class="font-semibold text-base-content">Input Data</span>
						{#if currentStep === 2}
							<span class="text-xs text-base-content opacity-70">Masukkan data perhitungan</span>
						{/if}
					</div>
				</li>
				<li class={`step ${currentStep >= 3 ? 'step-primary' : ''} ${!batchResult ? 'opacity-50' : ''}`}>
					<div class="flex flex-col items-center">
						<span class="font-semibold text-base-content">Hasil</span>
						{#if currentStep === 3 && batchResult}
							<span class="text-xs text-base-content opacity-70">Perhitungan selesai</span>
						{/if}
					</div>
				</li>
			</ul>
		</div>
	</div>

	<!-- Step 1: Pilih Pegawai -->
	{#if currentStep === 1}
		<div class="card bg-base-100 shadow-lg">
			<div class="card-body">
				<div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between mb-4">
					<h2 class="card-title text-base-content">Daftar Pegawai</h2>
					<div class="flex gap-2 w-full md:w-auto">
						<label class="input input-bordered flex items-center gap-2 flex-1 md:max-w-md">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
							</svg>
							<input
								type="text"
								class="grow text-base-content"
								placeholder="Cari nama atau NIK..."
								bind:value={searchQuery}
							/>
							{#if loadingEmployees}
								<span class="loading loading-spinner loading-sm"></span>
							{/if}
						</label>
					</div>
				</div>

				{#if loadingEmployees}
					<div class="flex justify-center items-center py-12">
						<span class="loading loading-spinner loading-lg text-primary"></span>
					</div>
				{:else}
					<div class="overflow-x-auto">
						<table class="table table-zebra">
							<thead>
								<tr>
									<th class="text-base-content w-12">
										<input
											type="checkbox"
											class="checkbox checkbox-primary checkbox-sm"
											checked={employees.length > 0 && employees.every(e => selectedEmployeeIds.has(e.id))}
											indeterminate={employees.some(e => selectedEmployeeIds.has(e.id)) && !employees.every(e => selectedEmployeeIds.has(e.id))}
											on:change={(e) => {
												if (e.currentTarget.checked) {
													employees.forEach(emp => toggleEmployeeSelection(emp));
												} else {
													employees.forEach(emp => {
														if (selectedEmployeeIds.has(emp.id)) {
															toggleEmployeeSelection(emp);
														}
													});
												}
											}}
										/>
									</th>
									<th class="text-base-content">Nama</th>
									<th class="text-base-content">NIK</th>
									<th class="text-base-content">Unit Kerja</th>
									<th class="text-base-content">Jenis</th>
									<th class="text-base-content">PTKP</th>
									<th class="text-base-content">NPWP</th>
								</tr>
							</thead>
							<tbody>
								{#if employees.length > 0}
									{#each employees as employee}
										<tr class="hover cursor-pointer" on:click={() => toggleEmployeeSelection(employee)}>
											<td>
												<input
													type="checkbox"
													class="checkbox checkbox-primary checkbox-sm"
													checked={selectedEmployeeIds.has(employee.id)}
													on:click|stopPropagation={() => toggleEmployeeSelection(employee)}
												/>
											</td>
											<td class="font-semibold text-base-content">{employee.person_name}</td>
											<td class="text-base-content opacity-70">{employee.nik}</td>
											<td class="text-base-content opacity-70">{employee.org_unit}</td>
											<td>
												<span class="badge badge-ghost badge-sm">{employee.employment_type}</span>
											</td>
											<td>
												<span class="badge badge-info badge-sm">{employee.ptkp_code}</span>
											</td>
											<td>
												{#if employee.has_npwp}
													<span class="badge badge-success badge-sm">Ya</span>
												{:else}
													<span class="badge badge-warning badge-sm">Tidak</span>
												{/if}
											</td>
										</tr>
									{/each}
								{:else}
									<tr>
										<td colspan="7" class="text-center text-base-content opacity-60 py-10">
											<div class="flex flex-col items-center gap-2">
												<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
												</svg>
												<p>Belum ada data pegawai</p>
												{#if searchQuery}
													<p class="text-sm">Coba gunakan kata kunci lain</p>
												{/if}
											</div>
										</td>
									</tr>
								{/if}
							</tbody>
						</table>
					</div>

					{#if pagination.total > 0}
						<div class="flex justify-between items-center mt-4 pt-4 border-t border-base-300">
							<div class="text-sm text-base-content opacity-70">
								Menampilkan {((pagination.current_page - 1) * pagination.per_page) + 1} - {Math.min(pagination.current_page * pagination.per_page, pagination.total)} dari {pagination.total} pegawai
							</div>
							<div class="join">
								<button
									class="btn btn-sm join-item"
									disabled={pagination.current_page === 1}
									on:click={() => loadEmployees(pagination.current_page - 1)}
								>
									«
								</button>
								<button class="btn btn-sm join-item" disabled>
									{pagination.current_page} / {pagination.last_page}
								</button>
								<button
									class="btn btn-sm join-item"
									disabled={pagination.current_page === pagination.last_page}
									on:click={() => loadEmployees(pagination.current_page + 1)}
								>
									»
								</button>
							</div>
						</div>
					{/if}
				{/if}
				
				<!-- Navigation Button -->
				<div class="flex justify-end mt-6 pt-4 border-t border-base-300">
					<button
						class="btn btn-brand"
						disabled={selectedEmployees.size === 0 || selectedEmployeeIds.size === 0}
						on:click={() => {
							if (selectedEmployees.size > 0) {
								currentStep = 2;
							}
						}}
					>
						Lanjut ke Input Data
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
						</svg>
					</button>
				</div>
			</div>
		</div>
	{/if}

	<!-- Step 2: Input Data Perhitungan -->
	{#if currentStep === 2}
		<div class="space-y-6">
			<!-- Sticky Settings Bar -->
			<div class="sticky top-4 z-10">
				<div class="card bg-base-100 shadow-lg border-2 border-primary/20">
					<div class="card-body py-4">
						<div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
							<!-- Mode Selection -->
							<div class="form-control">
								<div class="label pb-2">
									<span class="label-text font-semibold text-base-content">Mode Perhitungan</span>
								</div>
								<div class="join w-full">
									<button
										class="btn join-item flex-1 {calculationMode === 'monthly' ? 'btn-brand' : 'btn-ghost'}"
										on:click={() => calculationMode = 'monthly'}
									>
										Bulanan
									</button>
									<button
										class="btn join-item flex-1 {calculationMode === 'yearly' ? 'btn-brand' : 'btn-ghost'}"
										on:click={() => calculationMode = 'yearly'}
									>
										Tahunan
									</button>
								</div>
							</div>
							
							<!-- Period Selection -->
							<div class="form-control">
								<label for="month-select" class="label pb-2">
									<span class="label-text font-semibold text-base-content">Bulan</span>
								</label>
								<select id="month-select" class="select select-bordered w-full text-base-content" bind:value={month}>
									{#each monthOptions as option}
										<option value={option.value}>{option.label}</option>
									{/each}
								</select>
							</div>
							
							<div class="form-control">
								<label for="year-select" class="label pb-2">
									<span class="label-text font-semibold text-base-content">Tahun</span>
								</label>
								<select id="year-select" class="select select-bordered w-full text-base-content" bind:value={year}>
									{#each Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i) as y}
										<option value={y}>{y}</option>
									{/each}
								</select>
							</div>

							<!-- Selected Employees Summary -->
							<div class="form-control">
								<div class="label pb-2">
									<span class="label-text font-semibold text-base-content">Pegawai Terpilih</span>
								</div>
								<div class="flex items-center gap-2">
									<span class="badge badge-primary badge-lg">{selectedEmployees.size}</span>
									{#if selectedEmployees.size > 0}
										<button
											class="btn btn-xs btn-ghost"
											on:click={() => {
												selectedEmployeeIds.clear();
												selectedEmployees.clear();
												currentStep = 1;
											}}
											title="Hapus semua"
										>
											✕
										</button>
									{/if}
								</div>
							</div>
						</div>
						
						<!-- Mode Hint (Compact) -->
						<div class="mt-3 pt-3 border-t border-base-300">
							{#if calculationMode === 'monthly'}
								<div class="alert alert-info py-2">
									<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
									</svg>
									<div class="text-xs">
										<strong>Mode Bulanan:</strong> Input penghasilan bulanan, akan dikonversi ke tahunan (×12) untuk perhitungan.
										{#if month === 12}
											<span class="block mt-1 font-semibold">Desember akan melakukan rekonsiliasi tahunan</span>
										{:else}
											<span class="block mt-1">Menggunakan TER (Tarif Efektif Rata-rata)</span>
										{/if}
									</div>
								</div>
							{:else}
								<div class="alert alert-warning py-2">
									<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-4 w-4" fill="none" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
									</svg>
									<div class="text-xs">
										<strong>Mode Tahunan:</strong> Input penghasilan tahunan langsung. Digunakan untuk rekonsiliasi akhir tahun.
										<span class="block mt-1">Rekonsiliasi tahunan untuk tahun {year}</span>
									</div>
								</div>
							{/if}
						</div>
					</div>
				</div>
			</div>

			<!-- Selected Employees Compact List (Horizontal Scroll jika banyak) -->
			{#if selectedEmployees.size > 0}
				<div class="card bg-base-200 shadow-lg border border-base-300">
					<div class="card-body py-3">
						<div class="flex items-center gap-2 overflow-x-auto pb-2">
							<span class="text-sm font-semibold text-base-content flex-shrink-0">Pegawai Terpilih:</span>
							{#each Array.from(selectedEmployees.values()) as employee}
								<div class="badge badge-primary badge-lg gap-2 flex-shrink-0 text-primary-content">
									<span>{employee.person_name}</span>
									<button
										class="btn btn-xs btn-ghost btn-circle p-0 h-4 w-4 min-h-0 text-primary-content hover:bg-primary-focus"
										on:click={() => removeEmployee(employee.id)}
										title="Hapus"
									>
										✕
									</button>
								</div>
							{/each}
						</div>
					</div>
				</div>
			{/if}

			<!-- Calculation Forms (Natural Scroll) -->
			<div class="space-y-4">
				{#each Array.from(selectedEmployees.values()) as employee}
					<div class="card bg-base-100 shadow-lg">
						<div class="card-body">
							<div class="flex justify-between items-start mb-4">
								<div>
									<h3 class="card-title text-base-content">{employee.person_name}</h3>
									<p class="text-sm text-base-content opacity-70">
										{employee.org_unit} • PTKP: {employee.ptkp_code} • {employee.has_npwp ? 'NPWP: Ya' : 'NPWP: Tidak'}
									</p>
								</div>
								{#if getEmployeeResult(employee.id)}
									<span class="badge badge-success">Terhitung</span>
								{/if}
							</div>
							<div class="space-y-4">
								<!-- Earnings per Component -->
								<div class="form-control">
									<div class="label">
										<span class="label-text font-semibold text-base-content">
											Penghasilan (Earnings) <span class="text-error">*</span>
											{#if calculationMode === 'monthly'}
												<span class="badge badge-sm badge-info ml-2">Bulanan</span>
											{:else}
												<span class="badge badge-sm badge-warning ml-2">Tahunan</span>
											{/if}
										</span>
									</div>
									{#if components.length === 0}
										<div class="alert alert-warning">
											<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
											</svg>
											<span>Belum ada komponen earnings. Silakan buat komponen terlebih dahulu di menu Master Data.</span>
										</div>
									{:else}
										<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
											{#each components as component}
												<div class="form-control">
													<label for="earning-{employee.id}-{component.id}" class="label">
														<span class="label-text text-base-content">
															{component.name}
															{#if component.is_mandatory}
																<span class="badge badge-xs badge-error ml-1" title="Komponen wajib diisi">Wajib</span>
															{/if}
															{#if component.taxable}
																<span class="badge badge-xs badge-success ml-1" title="Komponen ini termasuk dalam perhitungan bruto (taxable)">Taxable</span>
															{:else}
																<span class="badge badge-xs badge-neutral ml-1" title="Komponen ini tidak termasuk dalam perhitungan bruto (non-taxable)">Non-Taxable</span>
															{/if}
														</span>
													</label>
													<label class="input input-bordered w-full text-base-content flex items-center gap-2 {component.taxable ? 'border-success/30 bg-success/5' : ''}">
														<span class="text-base-content opacity-70">Rp</span>
														<input
															id="earning-{employee.id}-{component.id}"
															type="text"
															class="grow text-base-content"
															placeholder="0"
															value={getEarningAmount(employee.id, component.id)}
															on:input={(e) => handleEarningInput(employee.id, component.id, e)}
														/>
													</label>
													{#if component.taxable}
														<div class="label">
															<span class="label-text-alt text-success opacity-70">
																✓ Termasuk dalam perhitungan bruto
															</span>
														</div>
													{/if}
												</div>
											{/each}
										</div>
										<!-- Total Bruto (Auto-calculated) -->
										<div class="form-control mt-4">
											<div class="label">
												<span class="label-text font-semibold text-base-content">
													Total Bruto (Otomatis)
													{#if calculationMode === 'monthly'}
														<span class="badge badge-sm badge-info ml-2">Bulanan</span>
													{:else}
														<span class="badge badge-sm badge-warning ml-2">Tahunan</span>
													{/if}
												</span>
											</div>
											<label class="input input-bordered w-full text-base-content text-lg flex items-center gap-2 bg-base-200">
												<span class="text-base-content opacity-70">Rp</span>
												<input
													type="text"
													class="grow text-base-content"
													value={formatNumber(calculateBruto(employee.id))}
													readonly
													disabled
												/>
											</label>
											<div class="label">
												<span class="label-text-alt text-base-content opacity-60">
													Total dari komponen taxable (hanya komponen dengan taxable: true)
													{#if calculationMode === 'monthly'}
														• Akan dikonversi ×12 untuk perhitungan
													{/if}
												</span>
											</div>
										</div>
									{/if}
								</div>
							</div>
							
							<!-- Deductions per Component -->
							<div class="form-control mt-4">
								<div class="label">
									<span class="label-text font-semibold text-base-content">
										Pengurang (Deductions)
										{#if calculationMode === 'monthly'}
											<span class="badge badge-sm badge-info ml-2">Bulanan</span>
										{:else}
											<span class="badge badge-sm badge-warning ml-2">Tahunan</span>
										{/if}
									</span>
								</div>
								
								<!-- Info untuk komponen auto-calculate -->
								<!-- Termasuk komponen dengan calculation_type === 'auto' DAN iuran_pensiun (selalu auto) -->
								{#if deductionComponents.filter(dc => dc.calculation_type === 'auto' || isIuranPensiun(dc)).length > 0}
									{@const autoDeductionComponents = deductionComponents.filter(dc => 
										dc.calculation_type === 'auto' || isIuranPensiun(dc)
									)}
									<div class="alert alert-info mb-4 py-2">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
										<div class="text-xs">
											<strong>Komponen Auto-calculate (tidak perlu input):</strong>
											<div class="flex flex-wrap gap-1 mt-1">
												{#each autoDeductionComponents as autoComponent}
													<div class="tooltip tooltip-top" data-tip="Komponen ini dihitung otomatis berdasarkan bruto. Biaya Jabatan: 5% dari bruto (maks 6 juta/tahun). Iuran Pensiun: 5% dari bruto (maks 2,4 juta/tahun).">
														<span class="badge badge-sm badge-info cursor-help">{autoComponent.name}</span>
													</div>
												{/each}
											</div>
											<span class="block mt-1">Komponen ini akan dihitung otomatis oleh sistem berdasarkan bruto. Hover pada badge untuk detail perhitungan.</span>
										</div>
									</div>
								{/if}
								
								<!-- Form input untuk komponen manual/percentage -->
								<!-- Filter: hanya komponen yang calculation_type !== 'auto' DAN bukan iuran_pensiun (karena iuran pensiun SELALU auto) -->
								{#if deductionComponents.filter(dc => dc.calculation_type !== 'auto' && !isIuranPensiun(dc)).length === 0}
									{@const manualDeductionComponents: DeductionComponent[] = []}
									<div class="alert alert-info">
										<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
										</svg>
										<span>Semua komponen deduction menggunakan perhitungan otomatis. Tidak perlu input manual.</span>
									</div>
								{:else}
									{@const manualDeductionComponents = deductionComponents.filter(dc => 
										dc.calculation_type !== 'auto' && !isIuranPensiun(dc)
									)}
									<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
										{#each manualDeductionComponents as deductionComponent}
											<div class="form-control">
												<label for="deduction-{employee.id}-{deductionComponent.id}" class="label">
													<span class="label-text text-base-content">
														{deductionComponent.name}
														{#if deductionComponent.type === 'mandatory'}
															<span class="badge badge-xs badge-error ml-1" title="Komponen wajib diisi">Wajib</span>
														{/if}
														{#if deductionComponent.is_tax_deductible}
															<span class="badge badge-xs badge-success ml-1" title="Komponen ini mengurangi penghasilan kena pajak (tax deductible)">Tax Deductible</span>
														{:else}
															<span class="badge badge-xs badge-neutral ml-1" title="Komponen ini tidak mengurangi penghasilan kena pajak (non-tax deductible)">Non-Tax Deductible</span>
														{/if}
														{#if deductionComponent.calculation_type === 'percentage'}
															<span class="badge badge-xs badge-warning ml-1" title="Komponen ini dihitung berdasarkan persentase">Percentage</span>
														{/if}
													</span>
												</label>
												<label class="input input-bordered w-full text-base-content flex items-center gap-2 {deductionComponent.is_tax_deductible ? 'border-success/30 bg-success/5' : ''}">
													<span class="text-base-content opacity-70">Rp</span>
													<input
														id="deduction-{employee.id}-{deductionComponent.id}"
														type="text"
														class="grow text-base-content"
														placeholder="0"
														value={getDeductionAmount(employee.id, deductionComponent.id)}
														on:input={(e) => handleDeductionInput(employee.id, deductionComponent.id, e)}
													/>
												</label>
												<div class="label">
													<span class="label-text-alt text-base-content opacity-60">
														{#if isZakat(deductionComponent)}
															{#if calculationMode === 'monthly'}
																Zakat bulanan (akan dikonversi ×12)
															{:else}
																Zakat tahunan
															{/if}
														{:else if deductionComponent.is_tax_deductible}
															<span class="text-success">Mengurangi penghasilan kena pajak</span>
														{:else}
															Input manual untuk {deductionComponent.name}
														{/if}
													</span>
												</div>
											</div>
										{/each}
									</div>
								{/if}
							</div>

							<!-- Quick Result Preview (Real-time) -->
							{#if employee.previewLoading}
								<div class="mt-4 pt-4 border-t border-base-300">
									<div class="flex justify-center">
										<span class="loading loading-spinner loading-sm text-primary"></span>
									</div>
								</div>
							{:else if employee.preview}
								<div class="mt-4 pt-4 border-t border-base-300">
									<div class="grid grid-cols-3 gap-2">
										<div class="stat bg-base-200 rounded-lg p-3">
											<div class="stat-title text-xs text-base-content opacity-70">
												Neto {calculationMode === 'monthly' ? '(Bulanan)' : '(Tahunan)'}
											</div>
											<div class="stat-value text-sm text-secondary">
												{formatCurrency(
													calculationMode === 'monthly'
														? (employee.preview.neto_masa || employee.preview.neto_year) / 12
														: (employee.preview.neto_year || employee.preview.neto_masa)
												)}
											</div>
										</div>
										<div class="stat bg-base-200 rounded-lg p-3">
											<div class="stat-title text-xs text-base-content opacity-70">PKP (Tahunan)</div>
											<div class="stat-value text-sm text-accent">{formatCurrency(employee.preview.pkp_year ?? employee.preview.pkp_annualized)}</div>
										</div>
										<div class="stat bg-base-200 rounded-lg p-3">
											<div class="stat-title text-xs text-base-content opacity-70">PPh 21 (Bulanan)</div>
											<div class="stat-value text-sm text-primary font-bold">{formatCurrency(employee.preview.pph21_masa)}</div>
										</div>
									</div>
								</div>
							{/if}
						</div>
					</div>
				{/each}
			</div>
			
			<!-- Action Buttons -->
			{#if selectedEmployees.size > 0}
				<div class="card bg-base-100 shadow-lg">
					<div class="card-body">
						<div class="flex gap-2">
							<button
								class="btn btn-brand flex-1"
								on:click={calculateBatch}
								disabled={loading || !Array.from(selectedEmployees.values()).some(e => e.calcData.bruto > 0)}
							>
								{#if loading}
									<span class="loading loading-spinner loading-sm"></span>
									Menghitung...
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
									</svg>
									Hitung PPh 21
								{/if}
							</button>
						</div>
						<!-- Navigation Buttons -->
						<div class="flex justify-between mt-4 pt-4 border-t border-base-300">
							<button
								class="btn btn-ghost"
								on:click={() => currentStep = 1}
							>
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
								</svg>
								Kembali
							</button>
						</div>
					</div>
				</div>
			{/if}
		</div>
	{/if}

	<!-- Step 3: Hasil Perhitungan -->
	{#if currentStep === 3}
		{#if loading}
			<div class="card bg-base-100 shadow-lg">
				<div class="card-body">
					<div class="flex justify-center items-center py-12">
						<span class="loading loading-spinner loading-lg text-primary"></span>
					</div>
				</div>
			</div>
		{:else if batchResult && batchResult.results.length > 0}
			<div class="space-y-6">
				<!-- Header dengan Info Periode -->
				<div class="card bg-base-100 shadow-lg">
					<div class="card-body">
						<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
							<div>
								<h2 class="card-title text-base-content mb-2">Hasil Perhitungan</h2>
								<div class="flex flex-wrap items-center gap-2 text-sm text-base-content opacity-70">
									<span class="badge badge-primary badge-lg">
										{calculationMode === 'monthly' ? 'Mode: Bulanan' : 'Mode: Tahunan'}
									</span>
									<span class="badge badge-secondary badge-lg">
										{monthOptions.find(m => m.value === month)?.label} {year}
									</span>
									<span class="badge badge-success badge-lg">
										Berhasil: {batchResult.success}
									</span>
									{#if batchResult.failed > 0}
										<span class="badge badge-error badge-lg">Gagal: {batchResult.failed}</span>
									{/if}
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Summary Stats -->
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div class="stat bg-base-100 shadow-lg rounded-lg border border-base-300">
						<div class="stat-title text-base-content opacity-70">PPh 21 Bulanan</div>
						<div class="stat-value text-primary text-2xl">
							{formatCurrency(batchResult.results.reduce((sum, r) => sum + (r.pph21_masa || 0), 0))}
						</div>
						<div class="stat-desc text-base-content opacity-60">
							Total untuk {monthOptions.find(m => m.value === month)?.label} {year}
						</div>
					</div>
					<div class="stat bg-base-100 shadow-lg rounded-lg border border-base-300">
						<div class="stat-title text-base-content opacity-70">Proyeksi PPh 21 Tahunan</div>
						<div class="stat-value text-accent text-2xl">
							{formatCurrency(
								calculationMode === 'monthly'
									? batchResult.results.reduce((sum, r) => sum + ((r.pph21_year || r.pph21_masa * 12) || 0), 0)
									: batchResult.results.reduce((sum, r) => sum + (r.pph21_year || 0), 0)
							)}
						</div>
						<div class="stat-desc text-base-content opacity-60">
							Estimasi total untuk tahun {year}
						</div>
					</div>
				</div>

				<!-- Tabel Hasil -->
				<div class="card bg-base-100 shadow-lg">
					<div class="card-body">
						<div class="overflow-x-auto">
						<table class="table table-zebra">
							<thead>
								<tr>
									<th class="text-base-content">Nama</th>
									<th class="text-base-content text-right">
										Bruto {calculationMode === 'monthly' ? '(Bulanan)' : '(Tahunan)'}
									</th>
									<th class="text-base-content text-right">
										Biaya Jabatan {calculationMode === 'monthly' ? '(Bulanan)' : '(Tahunan)'}
									</th>
									<th class="text-base-content text-right">
										Iuran Pensiun {calculationMode === 'monthly' ? '(Bulanan)' : '(Tahunan)'}
									</th>
									<th class="text-base-content text-right">
										Zakat {calculationMode === 'monthly' ? '(Bulanan)' : '(Tahunan)'}
									</th>
									<th class="text-base-content text-right">
										Neto {calculationMode === 'monthly' ? '(Bulanan)' : '(Tahunan)'}
									</th>
									<th class="text-base-content text-right">PPh 21 (Bulanan)</th>
								</tr>
							</thead>
							<tbody>
								{#each batchResult.results as result}
									<tr>
										<td class="text-base-content">
											<div>
												<div class="font-semibold">{result.person_name}</div>
												<div class="text-xs opacity-70">
													PTKP: {result.ptkp_code} • {result.has_npwp ? 'NPWP: Ya' : 'NPWP: Tidak'}
												</div>
											</div>
										</td>
										<td class="text-base-content text-right">
											{formatCurrency(
												calculationMode === 'monthly'
													? (result.bruto || 0) / 12
													: (result.bruto || 0)
											)}
										</td>
										<td class="text-base-content text-right">
											{formatCurrency(
												calculationMode === 'monthly'
													? (result.biaya_jabatan || 0) / 12
													: (result.biaya_jabatan || 0)
											)}
										</td>
										<td class="text-base-content text-right">
											{formatCurrency(
												calculationMode === 'monthly'
													? (result.iuran_pensiun || 0) / 12
													: (result.iuran_pensiun || 0)
											)}
										</td>
										<td class="text-base-content text-right">
											{formatCurrency(
												calculationMode === 'monthly'
													? (result.zakat || 0) / 12
													: (result.zakat || 0)
											)}
										</td>
										<td class="text-base-content text-right">
											{formatCurrency(
												calculationMode === 'monthly'
													? (result.neto_masa || result.neto_year || 0) / 12
													: (result.neto_masa || result.neto_year || 0)
											)}
										</td>
										<td class="text-base-content text-right font-bold text-primary">{formatCurrency(result.pph21_masa)}</td>
									</tr>
								{/each}
							</tbody>
							<tfoot>
								<tr class="font-bold">
									<td class="text-base-content">Total</td>
									<td class="text-base-content text-right">
										{formatCurrency(
											calculationMode === 'monthly'
												? batchResult.results.reduce((sum, r) => sum + (r.bruto || 0), 0) / 12
												: batchResult.results.reduce((sum, r) => sum + (r.bruto || 0), 0)
										)}
									</td>
									<td class="text-base-content text-right">
										{formatCurrency(
											calculationMode === 'monthly'
												? batchResult.results.reduce((sum, r) => sum + (r.biaya_jabatan || 0), 0) / 12
												: batchResult.results.reduce((sum, r) => sum + (r.biaya_jabatan || 0), 0)
										)}
									</td>
									<td class="text-base-content text-right">
										{formatCurrency(
											calculationMode === 'monthly'
												? batchResult.results.reduce((sum, r) => sum + (r.iuran_pensiun || 0), 0) / 12
												: batchResult.results.reduce((sum, r) => sum + (r.iuran_pensiun || 0), 0)
										)}
									</td>
									<td class="text-base-content text-right">
										{formatCurrency(
											calculationMode === 'monthly'
												? batchResult.results.reduce((sum, r) => sum + (r.zakat || 0), 0) / 12
												: batchResult.results.reduce((sum, r) => sum + (r.zakat || 0), 0)
										)}
									</td>
									<td class="text-base-content text-right">
										{formatCurrency(
											calculationMode === 'monthly'
												? batchResult.results.reduce((sum, r) => sum + (r.neto_masa || r.neto_year || 0), 0) / 12
												: batchResult.results.reduce((sum, r) => sum + (r.neto_masa || r.neto_year || 0), 0)
										)}
									</td>
									<td class="text-base-content text-right text-primary">
										{formatCurrency(batchResult.results.reduce((sum, r) => sum + (r.pph21_masa || 0), 0))}
									</td>
								</tr>
							</tfoot>
						</table>
						</div>

						<!-- Notes -->
						<div class="mt-6 pt-4 border-t border-base-300">
							<div class="alert alert-info">
								<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
								</svg>
								<div class="text-sm text-base-content">
									<h3 class="font-bold mb-2">Catatan Penting</h3>
									<ul class="list-disc list-inside space-y-1">
										<li>Biaya Jabatan: 5% dari bruto, maksimal 500.000/bulan</li>
										<li>Iuran Pensiun: 5% dari bruto, maksimal 200.000/bulan</li>
										<li>Tanpa NPWP dikenakan tarif 20% lebih tinggi</li>
										{#if month === 12}
											<li>Desember: Akan dilakukan rekonsiliasi tahunan</li>
										{:else}
											<li>Perhitungan menggunakan TER (Tarif Efektif Rata-rata)</li>
										{/if}
										{#if calculationMode === 'monthly'}
											<li>Proyeksi tahunan dihitung berdasarkan asumsi penghasilan tetap selama 12 bulan</li>
										{/if}
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="card bg-base-100 shadow-lg">
					<div class="card-body">
						<div class="flex flex-col sm:flex-row gap-3 justify-between items-center">
							<button
								class="btn btn-ghost w-full sm:w-auto"
								on:click={() => currentStep = 2}
							>
								<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
								</svg>
								Kembali ke Input Data
							</button>
							<div class="flex gap-2 w-full sm:w-auto">
								<button
									class="btn btn-ghost hover:bg-primary hover:text-white flex-1 sm:flex-none"
									on:click={() => {
										selectedEmployeeIds.clear();
										selectedEmployees.clear();
										batchResult = null;
										currentStep = 1;
									}}
								>
									<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
									</svg>
									Hitung Lagi
								</button>
								<button
									class="btn btn-success flex-1 sm:flex-none"
									on:click={saveToHistory}
									disabled={savingHistory}
								>
									{#if savingHistory}
										<span class="loading loading-spinner loading-sm"></span>
										Menyimpan...
									{:else}
										<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
										</svg>
										Simpan Data
									{/if}
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		{/if}
	{/if}
</div>

<!-- Modal Warning Duplikasi -->
{#if showDuplicateWarning && duplicateEmployees.length > 0}
	<dialog bind:this={duplicateWarningModal} id="duplicate-warning-modal" class="modal">
		<div class="modal-box bg-base-100 text-base-content">
			<h3 class="font-bold text-lg mb-4 text-base-content flex items-center gap-2">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
				</svg>
				Peringatan: Data Akan Tertimpa
			</h3>

			<div class="space-y-4">
				<div class="alert alert-warning">
					<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
					</svg>
					<div>
						<p class="font-semibold">Anda sudah melakukan perhitungan untuk pegawai berikut di bulan {monthOptions.find(m => m.value === month)?.label} {year}.</p>
						<p class="text-sm mt-1">Jika Anda melanjutkan, data perhitungan yang sudah ada akan tertimpa dengan data baru.</p>
					</div>
				</div>

				<div class="bg-base-200 rounded-lg p-4">
					<div class="text-sm font-semibold text-base-content mb-2">Pegawai yang akan tertimpa:</div>
					<ul class="list-disc list-inside space-y-1">
						{#each duplicateEmployees as dup}
							<li class="text-base-content">{dup.person_name}</li>
						{/each}
					</ul>
				</div>
			</div>

			<div class="modal-action mt-6">
				<button class="btn btn-ghost" on:click={handleCancelCalculation}>
					Batal
				</button>
				<button class="btn btn-warning" on:click={handleConfirmCalculation}>
					Lanjut
				</button>
			</div>
		</div>
		<form method="dialog" class="modal-backdrop" on:submit|preventDefault={handleCancelCalculation}>
			<button>close</button>
		</form>
	</dialog>
{/if}
