import { CommonModule, CurrencyPipe, NgOptimizedImage } from '@angular/common';
import { Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { CartStateService, ProductListItem, ProductosService } from '../../servicios';

type VistaHome = 'store' | 'inventory';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink, CurrencyPipe, NgOptimizedImage],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit {
  private readonly productosService = inject(ProductosService);
  private readonly cartState = inject(CartStateService);
  private readonly destroyRef = inject(DestroyRef);

  private toastHandle: ReturnType<typeof setTimeout> | null = null;

  protected readonly productos = signal<ProductListItem[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly mensaje = signal<string | null>(null);

  protected readonly totalItems = computed(() => this.cartState.totalItems());
  protected readonly totalAmount = computed(() => this.cartState.totalAmount());

  protected readonly activeView = signal<VistaHome>('store');
  protected readonly query = signal('');
  protected readonly selectedCategory = signal('Todos');

  private readonly lastUpdated = signal<Date | null>(null);

  protected readonly categorias = computed(() => {
    const names = new Set(
      this.productos()
        .map((item) => (item.category ?? '').trim())
        .filter((value): value is string => value.length > 0)
    );
    const ordenadas = Array.from(names).sort((a, b) => a.localeCompare(b, 'es'));
    return ['Todos', ...ordenadas];
  });

  protected readonly filteredProductos = computed(() => {
    const term = this.query().trim().toLowerCase();
    const category = this.selectedCategory();

    return this.productos().filter((item) => {
      const matchesCategory =
        category === 'Todos' || item.category.toLowerCase() === category.toLowerCase();
      if (!matchesCategory) {
        return false;
      }

      if (!term) {
        return true;
      }

      return [item.name, item.artisan, item.category, item.sku]
        .filter((value): value is string => Boolean(value))
        .some((value) => value.toLowerCase().includes(term));
    });
  });

  protected readonly productCountLabel = computed(() => {
    const count = this.filteredProductos().length;
    if (!count) {
      return 'Sin coincidencias';
    }
    return `${count} ${count === 1 ? 'pieza disponible' : 'piezas disponibles'}`;
  });

  protected readonly filtrosActivos = computed(
    () => this.query().trim().length > 0 || this.selectedCategory() !== 'Todos'
  );

  protected readonly inventorySummary = computed(() => {
    const items = this.productos();
    const totalUnits = items.reduce((acc, item) => acc + Number(item.stock ?? 0), 0);
    const totalValue = items.reduce(
      (acc, item) => acc + Number(item.stock ?? 0) * Number(item.price ?? 0),
      0
    );
    return {
      totalProducts: items.length,
      totalUnits,
      totalValue
    };
  });

  protected readonly lowStock = computed(() =>
    this.productos().filter((item) => Number(item.stock ?? 0) <= 5)
  );

  protected readonly inventoryTimestamp = computed(() => {
    const timestamp = this.lastUpdated();
    if (!timestamp) {
      return null;
    }
    return new Intl.DateTimeFormat('es-EC', {
      dateStyle: 'medium',
      timeStyle: 'short'
    }).format(timestamp);
  });

  protected readonly placeholder =
    'https://images.unsplash.com/photo-1523381294911-8d3cead13475?auto=format&fit=crop&w=600&q=70';

  constructor() {
    this.destroyRef.onDestroy(() => {
      if (this.toastHandle) {
        clearTimeout(this.toastHandle);
      }
    });
  }

  ngOnInit(): void {
    this.cargarProductos();
  }

  cargarProductos(): void {
    this.cargando.set(true);
    this.error.set(null);
    this.productosService
      .listarProductos({ limit: 60 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => {
          this.productos.set(resp.data ?? []);
          this.lastUpdated.set(new Date());
          this.cargando.set(false);
        },
        error: (err) => {
          console.error('Error cargando productos', err);
          this.error.set('No pudimos cargar el catalogo. Intenta nuevamente.');
          this.cargando.set(false);
        }
      });
  }

  cambiarVista(vista: VistaHome): void {
    if (this.activeView() !== vista) {
      this.activeView.set(vista);
    }
  }

  actualizarBusqueda(valor: string): void {
    this.query.set(valor);
    if (this.activeView() !== 'store') {
      this.activeView.set('store');
    }
  }

  seleccionarCategoria(categoria: string): void {
    this.selectedCategory.set(categoria);
    if (this.activeView() !== 'store') {
      this.activeView.set('store');
    }
  }

  limpiarFiltros(): void {
    this.query.set('');
    this.selectedCategory.set('Todos');
  }

  agregarAlCarrito(producto: ProductListItem): void {
    this.cartState.addItem(producto, 1);
    this.mensaje.set(`${producto.name} se agrego al carrito.`);
    if (this.toastHandle) {
      clearTimeout(this.toastHandle);
    }
    this.toastHandle = setTimeout(() => {
      this.mensaje.set(null);
      this.toastHandle = null;
    }, 2500);
  }

  importeProducto(producto: ProductListItem): number {
    return producto.price;
  }

  trackByProducto(_: number, item: ProductListItem): number {
    return item.product_id;
  }
}
