import { apiGet, apiPost, apiPatch } from './client.js';

export interface Person {
	id: string;
	tenant_id: number;
	full_name: string;
	nik: string | null;
	npwp: string | null;
	birth_date: string | null;
	created_at: string;
	updated_at: string;
	identifiers?: PersonIdentifier[];
}

export interface PersonIdentifier {
	id: number;
	person_id: string;
	scheme_id: number;
	raw_value: string;
	norm_value: string;
	created_at?: string;
	scheme?: {
		id: number;
		code: string;
		label: string;
	};
}

export interface PaginatedResponse<T> {
	data: T[];
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
}

export const personsApi = {
	async list(params?: {
		search?: string;
		identifier?: string;
		per_page?: number;
		page?: number;
	}): Promise<PaginatedResponse<Person>> {
		return await apiGet<PaginatedResponse<Person>>('/persons', params);
	},
	
	async get(id: string): Promise<Person> {
		return await apiGet<Person>(`/persons/${id}`);
	},
	
	async create(person: {
		full_name: string;
		nik?: string | null;
		npwp?: string | null;
		birth_date?: string | null;
	}): Promise<Person> {
		return await apiPost<Person>('/persons', person);
	},
	
	async update(id: string, person: Partial<{
		full_name: string;
		nik: string | null;
		npwp: string | null;
		birth_date: string | null;
	}>): Promise<Person> {
		return await apiPatch<Person>(`/persons/${id}`, person);
	},
	
	async resolve(query: string): Promise<Person | null> {
		return await apiGet<Person | null>('/persons/resolve', { q: query });
	},
	
	async addIdentifier(personId: string, identifier: {
		scheme_id: number;
		raw_value: string;
	}): Promise<PersonIdentifier> {
		return await apiPost<PersonIdentifier>(`/persons/${personId}/identifiers`, identifier);
	},
	
	async checkUnique(schemeId: number, normValue: string, scopeEntityId?: number): Promise<{ is_unique: boolean }> {
		return await apiGet<{ is_unique: boolean }>('/identifiers/check-unique', {
			scheme_id: schemeId,
			norm_value: normValue,
			scope_entity_id: scopeEntityId
		});
	}
};

