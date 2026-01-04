import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import {
  AddCartItemPayload,
  ApiResponse,
  CartListItem,
  CartSummary,
  PaginatedResponse,
  UpdateCartItemPayload,
  UpdateCartPayload,
  CreateCartPayload
} from './api-types';

export interface FiltrosCarrito extends Record<string, unknown> {
  page?: number;
  limit?: number;
}

@Injectable({
  providedIn: 'root'
})
export class CarritosService {
  constructor(private readonly api: ApiClientService) {}

  listarCarritos(filtros: FiltrosCarrito = {}): Observable<PaginatedResponse<CartListItem[]>> {
    return this.api.get<PaginatedResponse<CartListItem[]>>('carts', filtros);
  }

  obtenerCarrito(id: number | string): Observable<ApiResponse<CartSummary>> {
    return this.api.get<ApiResponse<CartSummary>>('carts', { id });
  }

  crearCarrito(payload: CreateCartPayload): Observable<ApiResponse<CartSummary>> {
    return this.api.post<ApiResponse<CartSummary>>('carts', payload);
  }

  actualizarCarrito(id: number | string, cambios: UpdateCartPayload): Observable<ApiResponse<CartSummary>> {
    return this.api.put<ApiResponse<CartSummary>>('carts', cambios, { id });
  }

  eliminarCarrito(id: number | string): Observable<ApiResponse<unknown>> {
    return this.api.delete<ApiResponse<unknown>>('carts', { id });
  }

  agregarItem(payload: AddCartItemPayload): Observable<ApiResponse<CartSummary>> {
    return this.api.post<ApiResponse<CartSummary>>('cart_items', payload);
  }

  actualizarItem(payload: UpdateCartItemPayload): Observable<ApiResponse<CartSummary>> {
    return this.api.put<ApiResponse<CartSummary>>('cart_items', payload);
  }

  eliminarItem(cartItemId: number | string): Observable<ApiResponse<CartSummary>> {
    return this.api.delete<ApiResponse<CartSummary>>('cart_items', { id: cartItemId });
  }
}
