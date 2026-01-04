import { HttpClient, HttpParams } from '@angular/common/http';
import { Inject, Injectable, InjectionToken, Optional } from '@angular/core';
import { Observable } from 'rxjs';

export const API_BASE_URL = new InjectionToken<string>('API_BASE_URL');

@Injectable({
  providedIn: 'root'
})
export class ApiClientService {
  private readonly baseUrl: string;

  constructor(
    private readonly http: HttpClient,
    @Optional() @Inject(API_BASE_URL) baseUrl?: string
  ) {
    // Ajusta la URL si despliegas el backend en otra ruta o dominio.
    this.baseUrl = baseUrl ?? 'http://localhost/api/api.php';
  }

  get<T>(resource: string, params?: Record<string, unknown>): Observable<T> {
    return this.http.get<T>(this.baseUrl, {
      params: this.buildParams(resource, params)
    });
  }

  post<T>(resource: string, body: unknown, params?: Record<string, unknown>): Observable<T> {
    return this.http.post<T>(this.baseUrl, body, {
      params: this.buildParams(resource, params)
    });
  }

  put<T>(resource: string, body: unknown, params?: Record<string, unknown>): Observable<T> {
    return this.http.put<T>(this.baseUrl, body, {
      params: this.buildParams(resource, params)
    });
  }

  delete<T>(resource: string, params?: Record<string, unknown>): Observable<T> {
    return this.http.delete<T>(this.baseUrl, {
      params: this.buildParams(resource, params)
    });
  }

  private buildParams(resource: string, params?: Record<string, unknown>): HttpParams {
    let httpParams = new HttpParams().set('resource', resource);

    if (params) {
      Object.entries(params)
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .forEach(([key, value]) => {
          if (Array.isArray(value)) {
            value.forEach((v) => {
              httpParams = httpParams.append(key, String(v));
            });
          } else {
            httpParams = httpParams.set(key, String(value));
          }
        });
    }

    return httpParams;
  }
}
