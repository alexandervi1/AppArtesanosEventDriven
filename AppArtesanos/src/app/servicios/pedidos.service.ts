import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import { ApiResponse, CreateOrderPayload, OrderDetail, OrderListItem, PaginatedResponse } from './api-types';

export interface FiltrosPedido extends Record<string, unknown> {
  page?: number;
  limit?: number;
}

@Injectable({
  providedIn: 'root'
})
export class PedidosService {
  constructor(private readonly api: ApiClientService) {}

  listarPedidos(filtros: FiltrosPedido = {}): Observable<PaginatedResponse<OrderListItem[]>> {
    return this.api.get<PaginatedResponse<OrderListItem[]>>('orders', filtros);
  }

  obtenerPedido(id: number | string): Observable<ApiResponse<OrderDetail>> {
    return this.api.get<ApiResponse<OrderDetail>>('orders', { id });
  }

  crearPedido(payload: CreateOrderPayload): Observable<ApiResponse<OrderDetail>> {
    return this.api.post<ApiResponse<OrderDetail>>('orders', payload);
  }

  eliminarPedido(id: number | string): Observable<ApiResponse<unknown>> {
    return this.api.delete<ApiResponse<unknown>>('orders', { id });
  }
}
