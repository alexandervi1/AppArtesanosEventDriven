import { Injectable, OnDestroy, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject, fromEvent } from 'rxjs';
import { filter, map } from 'rxjs/operators';

export interface DatosPedidoCreado {
  numero_pedido?: number;
  total?: number;
  correo_cliente?: string;
  [clave: string]: unknown;
}

export interface EventoPedidoCreado {
  tipo: 'pedido-creado';
  datos: DatosPedidoCreado;
  recibidoEn: string;
}

const RETARDO_REINTENTO_MS = 2000;

@Injectable({
  providedIn: 'root'
})
export class EventosPedidosService implements OnDestroy {
  private readonly urlWebSocket = signal<string>('ws://localhost:3001');
  private readonly conectado = signal(false);
  private readonly socketActual = signal<WebSocket | null>(null);
  private readonly eventosInternos = new Subject<EventoPedidoCreado>();
  private reintentoProgramado: ReturnType<typeof setTimeout> | null = null;

  readonly pedidoCreado$ = this.eventosInternos.asObservable();
  readonly ultimoEvento = signal<EventoPedidoCreado | null>(null);

  ngOnDestroy(): void {
    this.cerrarConexion();
  }

  configurarUrlWebSocket(url: string): void {
    if (this.conectado()) {
      this.cerrarConexion();
    }
    this.urlWebSocket.set(url);
  }

  conectar(): void {
    if (this.conectado()) {
      return;
    }

    const url = this.urlWebSocket();
    try {
      const socket = new WebSocket(url);
      this.socketActual.set(socket);

      fromEvent(socket, 'open')
        .pipe(takeUntilDestroyed())
        .subscribe(() => {
          this.conectado.set(true);
        });

      fromEvent<MessageEvent>(socket, 'message')
        .pipe(
          map((evento) => this.convertirMensaje(evento.data)),
          filter((evento): evento is EventoPedidoCreado => evento !== null),
          takeUntilDestroyed()
        )
        .subscribe((evento) => {
          this.ultimoEvento.set(evento);
          this.eventosInternos.next(evento);
        });

      fromEvent(socket, 'close')
        .pipe(takeUntilDestroyed())
        .subscribe(() => {
          this.conectado.set(false);
          this.socketActual.set(null);
          this.programarReconexion();
        });

      fromEvent(socket, 'error')
        .pipe(takeUntilDestroyed())
        .subscribe(() => {
          socket.close();
        });
    } catch (error) {
      console.error('[EventosPedidosService] No se pudo abrir el WebSocket', error);
      this.programarReconexion();
    }
  }

  private convertirMensaje(data: unknown): EventoPedidoCreado | null {
    try {
      if (typeof data !== 'string') {
        return null;
      }
      const evento = JSON.parse(data) as Partial<EventoPedidoCreado>;
      if (evento?.tipo !== 'pedido-creado' || !evento.datos) {
        return null;
      }
      return {
        tipo: 'pedido-creado',
        datos: evento.datos as DatosPedidoCreado,
        recibidoEn: evento.recibidoEn ?? new Date().toISOString()
      };
    } catch (error) {
      console.error('[EventosPedidosService] Error interpretando mensaje', error);
      return null;
    }
  }

  private programarReconexion(): void {
    if (this.conectado()) {
      return;
    }
    if (this.reintentoProgramado) {
      return;
    }
    this.reintentoProgramado = setTimeout(() => {
      this.reintentoProgramado = null;
      if (!this.conectado()) {
        this.conectar();
      }
    }, RETARDO_REINTENTO_MS);
  }

  private cerrarConexion(): void {
    const socket = this.socketActual();
    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.close();
    }
    this.socketActual.set(null);
    this.conectado.set(false);
    if (this.reintentoProgramado) {
      clearTimeout(this.reintentoProgramado);
      this.reintentoProgramado = null;
    }
  }
}
