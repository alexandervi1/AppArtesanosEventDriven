import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import { ApiResponse, Artisan } from './api-types';

@Injectable({
  providedIn: 'root'
})
export class ArtesanosService {
  constructor(private readonly api: ApiClientService) {}

  obtenerArtesanos(): Observable<ApiResponse<Artisan[]>> {
    return this.api.get<ApiResponse<Artisan[]>>('artisans');
  }

  obtenerArtesano(id: number | string): Observable<ApiResponse<Artisan>> {
    return this.api.get<ApiResponse<Artisan>>('artisans', { id });
  }

  crearArtesano(payload: Pick<Artisan, 'workshop_name'> & Partial<Omit<Artisan, 'artisan_id' | 'created_at' | 'updated_at'>>): Observable<ApiResponse<Artisan>> {
    return this.api.post<ApiResponse<Artisan>>('artisans', payload);
  }

  actualizarArtesano(id: number | string, cambios: Partial<Omit<Artisan, 'artisan_id' | 'created_at' | 'updated_at'>>): Observable<ApiResponse<Artisan>> {
    return this.api.put<ApiResponse<Artisan>>('artisans', cambios, { id });
  }

  eliminarArtesano(id: number | string): Observable<ApiResponse<unknown>> {
    return this.api.delete<ApiResponse<unknown>>('artisans', { id });
  }
}
