import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClientService } from './api-client.service';
import { PingStatus } from './api-types';

@Injectable({
  providedIn: 'root'
})
export class SaludService {
  constructor(private readonly api: ApiClientService) {}

  verificarEstado(): Observable<PingStatus> {
    return this.api.get<PingStatus>('ping');
  }
}
