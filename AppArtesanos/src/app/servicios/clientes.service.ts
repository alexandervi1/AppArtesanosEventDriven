import { Injectable } from '@angular/core';
import { Observable, of, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';

import { ApiClientService } from './api-client.service';
import { ApiResponse, Customer, CustomerPayload, PaginatedResponse, UpdateCustomerPayload } from './api-types';

export interface FiltrosClientes extends Record<string, unknown> {
  page?: number;
  limit?: number;
}

@Injectable({
  providedIn: 'root'
})
export class ClientesService {
  constructor(private readonly api: ApiClientService) {}

  listarClientes(filtros: FiltrosClientes = {}): Observable<PaginatedResponse<Customer[]>> {
    return this.api.get<PaginatedResponse<Customer[]>>('customers', filtros);
  }

  buscarPorEmail(email: string): Observable<ApiResponse<Customer | null>> {
    return this.api.get<ApiResponse<Customer | null>>('customers', { email }).pipe(
      catchError((error) => {
        if (error?.status === 404) {
          return of({ ok: true, data: null } as ApiResponse<Customer | null>);
        }
        return throwError(() => error);
      })
    );
  }

  obtenerCliente(id: number | string): Observable<ApiResponse<Customer>> {
    return this.api.get<ApiResponse<Customer>>('customers', { id });
  }

  crearCliente(payload: CustomerPayload): Observable<ApiResponse<Customer>> {
    return this.api.post<ApiResponse<Customer>>('customers', payload);
  }

  actualizarCliente(id: number | string, cambios: UpdateCustomerPayload): Observable<ApiResponse<Customer>> {
    return this.api.put<ApiResponse<Customer>>('customers', cambios, { id });
  }

  eliminarCliente(id: number | string): Observable<ApiResponse<unknown>> {
    return this.api.delete<ApiResponse<unknown>>('customers', { id });
  }
}
