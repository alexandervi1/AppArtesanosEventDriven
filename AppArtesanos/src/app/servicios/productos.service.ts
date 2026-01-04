import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import {
  ApiResponse,
  CreateProductPayload,
  PaginatedResponse,
  Product,
  ProductListItem
} from './api-types';

export interface FiltrosProductos extends Record<string, unknown> {
  category_slug?: string;
  artisan?: string;
  q?: string;
  page?: number;
  limit?: number;
}

@Injectable({
  providedIn: 'root'
})
export class ProductosService {
  constructor(private readonly api: ApiClientService) {}

  listarProductos(filtros: FiltrosProductos = {}): Observable<PaginatedResponse<ProductListItem[]>> {
    return this.api.get<PaginatedResponse<ProductListItem[]>>('products', filtros);
  }

  obtenerProductoPorId(id: number | string): Observable<ApiResponse<Product | null>> {
    return this.api.get<ApiResponse<Product | null>>('products', { id });
  }

  registrarProducto(payload: CreateProductPayload): Observable<ApiResponse<Product | null>> {
    return this.api.post<ApiResponse<Product | null>>('products', payload);
  }

  actualizarProducto(id: number | string, cambios: Partial<Product> & Partial<CreateProductPayload>): Observable<ApiResponse<Product>> {
    return this.api.put<ApiResponse<Product>>('products', cambios, { id });
  }

  eliminarProducto(id: number | string): Observable<ApiResponse<unknown>> {
    return this.api.delete<ApiResponse<unknown>>('products', { id });
  }
}
