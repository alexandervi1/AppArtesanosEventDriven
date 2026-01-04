import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { EventosPedidosService, OrderListItem, PedidosService } from '../../../servicios';

interface SalesResume {
  totalOrders: number;
  totalRevenue: number;
  averageTicket: number;
  pending: number;
  completed: number;
  cancelled: number;
  totalTax: number;
  totalShipping: number;
}

interface DailySales {
  date: Date;
  total: number;
  orders: number;
}

@Component({
  selector: 'app-resumen-ventas',
  standalone: true,
  imports: [CommonModule, RouterLink, CurrencyPipe],
  templateUrl: './resumen-ventas.component.html',
  styleUrls: ['./resumen-ventas.component.css']
})
export class ResumenVentasComponent implements OnInit {
  private readonly pedidosService = inject(PedidosService);
  private readonly eventosPedidos = inject(EventosPedidosService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly pedidos = signal<OrderListItem[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly lastUpdated = signal<Date | null>(null);

  protected readonly resumen = computed<SalesResume>(() => {
    const orders = this.pedidos();
    const totalOrders = orders.length;

    const totals = orders.reduce(
      (acc, order) => {
        const total = Number(order.total ?? 0);
        const tax = Number(order.tax ?? 0);
        const shipping = Number(order.shipping_cost ?? 0);
        acc.totalRevenue += total;
        acc.totalTax += tax;
        acc.totalShipping += shipping;
        if (order.status === 'completed' || order.status === 'fulfilled') {
          acc.completed += 1;
        } else if (order.status === 'cancelled') {
          acc.cancelled += 1;
        } else {
          acc.pending += 1;
        }
        return acc;
      },
      {
        totalRevenue: 0,
        totalTax: 0,
        totalShipping: 0,
        pending: 0,
        completed: 0,
        cancelled: 0
      }
    );

    return {
      totalOrders,
      totalRevenue: totals.totalRevenue,
      averageTicket: totalOrders ? totals.totalRevenue / totalOrders : 0,
      pending: totals.pending,
      completed: totals.completed,
      cancelled: totals.cancelled,
      totalTax: totals.totalTax,
      totalShipping: totals.totalShipping
    };
  });

  protected readonly ventasPorDia = computed<DailySales[]>(() => {
    const grouped = new Map<string, DailySales>();

    this.pedidos().forEach((order) => {
      const placedAt = order.placed_at ?? order.updated_at;
      if (!placedAt) {
        return;
      }
      const date = new Date(placedAt);
      if (Number.isNaN(date.getTime())) {
        return;
      }
      const key = date.toISOString().slice(0, 10);
      const entry = grouped.get(key);
      const total = Number(order.total ?? 0);
      if (entry) {
        entry.total += total;
        entry.orders += 1;
      } else {
        grouped.set(key, { date, total, orders: 1 });
      }
    });

    return Array.from(grouped.values()).sort((a, b) => b.date.getTime() - a.date.getTime());
  });

  protected readonly ultimosPedidos = computed(() => {
    return [...this.pedidos()].sort((a, b) => {
      const dateA = this.toDate(a.placed_at ?? a.updated_at);
      const dateB = this.toDate(b.placed_at ?? b.updated_at);
      return (dateB?.getTime() ?? 0) - (dateA?.getTime() ?? 0);
    });
  });

  protected readonly tienePedidos = computed(() => this.pedidos().length > 0);

  ngOnInit(): void {
    this.cargarPedidos();
    this.eventosPedidos.conectar();
    this.eventosPedidos.pedidoCreado$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.cargarPedidos();
      });
  }

  cargarPedidos(): void {
    this.cargando.set(true);
    this.error.set(null);
    this.pedidosService
      .listarPedidos({ limit: 120 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => {
          this.pedidos.set(resp.data ?? []);
          this.lastUpdated.set(new Date());
          this.cargando.set(false);
        },
        error: (err) => {
          console.error('Error cargando pedidos', err);
          this.error.set('No pudimos recuperar las ventas. Intenta nuevamente.');
          this.cargando.set(false);
        }
      });
  }

  protected resumenPorEstado(): Array<{ status: string; cantidad: number }> {
    const counts = new Map<string, number>();
    this.pedidos().forEach((order) => {
      const key = order.status ?? 'sin-estado';
      counts.set(key, (counts.get(key) ?? 0) + 1);
    });

    return Array.from(counts.entries())
      .map(([status, cantidad]) => ({ status, cantidad }))
      .sort((a, b) => b.cantidad - a.cantidad);
  }

  protected formatearEstado(status: string | null | undefined): string {
    if (!status) return 'Sin estado';
    const diccionario: Record<string, string> = {
      pending: 'Pendiente',
      paid: 'Pagado',
      fulfilled: 'Despachado',
      shipped: 'Enviado',
      completed: 'Completado',
      cancelled: 'Cancelado',
      failed: 'Fallido',
      refunded: 'Reembolsado'
    };
    return diccionario[status] ?? status.replace(/[_-]/g, ' ');
  }

  protected claseEstado(status: string | null | undefined): string {
    switch (status) {
      case 'completed':
      case 'fulfilled':
        return 'bg-emerald-100 text-emerald-700';
      case 'paid':
        return 'bg-emerald-50 text-emerald-600';
      case 'pending':
        return 'bg-amber-100 text-amber-700';
      case 'shipped':
        return 'bg-sky-100 text-sky-700';
      case 'cancelled':
      case 'failed':
        return 'bg-rose-100 text-rose-700';
      case 'refunded':
        return 'bg-indigo-100 text-indigo-700';
      default:
        return 'bg-slate-100 text-slate-600';
    }
  }

  protected formatoTimestamp(): string {
    const value = this.lastUpdated();
    if (!value) {
      return 'Sincronizando...';
    }
    return new Intl.DateTimeFormat('es-EC', {
      dateStyle: 'medium',
      timeStyle: 'medium'
    }).format(value);
  }

  protected trackByOrder(_: number, order: OrderListItem): number {
    return order.order_id;
  }

  protected trackByDia(_: number, item: DailySales): string {
    return item.date.toISOString();
  }

  private toDate(value?: string | null): Date | null {
    if (!value) {
      return null;
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  }
}
