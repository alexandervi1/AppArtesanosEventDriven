import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import {
  ApiResponse,
  CatalogItem,
  CategoryTotalsRow,
  InventoryOverview,
  LowStockProduct
} from './api-types';

@Injectable({
  providedIn: 'root'
})
export class CatalogoService {
  constructor(private readonly api: ApiClientService) {}

  obtenerCatalogo(): Observable<ApiResponse<CatalogItem[]>> {
    return this.api.get<ApiResponse<CatalogItem[]>>('catalog');
  }

  obtenerProductosConStockBajo(): Observable<ApiResponse<LowStockProduct[]>> {
    return this.api.get<ApiResponse<LowStockProduct[]>>('low_stock');
  }

  obtenerResumenInventario(): Observable<ApiResponse<InventoryOverview>> {
    return this.api.get<ApiResponse<InventoryOverview>>('inventory_overview');
  }

  obtenerTotalesPorCategoria(): Observable<ApiResponse<CategoryTotalsRow[]>> {
    return this.api.get<ApiResponse<CategoryTotalsRow[]>>('category_totals');
  }
}
