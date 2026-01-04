import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import { ApiResponse, Category } from './api-types';

@Injectable({
  providedIn: 'root'
})
export class CategoriasService {
  constructor(private readonly api: ApiClientService) {}

  obtenerCategorias(): Observable<ApiResponse<Category[]>> {
    return this.api.get<ApiResponse<Category[]>>('categories');
  }

  obtenerCategoria(id: number | string): Observable<ApiResponse<Category>> {
    return this.api.get<ApiResponse<Category>>('categories', { id });
  }

  crearCategoria(payload: Pick<Category, 'name' | 'slug'> & { description?: string | null }): Observable<ApiResponse<Category>> {
    return this.api.post<ApiResponse<Category>>('categories', payload);
  }

  actualizarCategoria(id: number | string, cambios: Partial<Pick<Category, 'name' | 'slug' | 'description'>>): Observable<ApiResponse<Category>> {
    return this.api.put<ApiResponse<Category>>('categories', cambios, { id });
  }

  eliminarCategoria(id: number | string): Observable<ApiResponse<unknown>> {
    return this.api.delete<ApiResponse<unknown>>('categories', { id });
  }
}
