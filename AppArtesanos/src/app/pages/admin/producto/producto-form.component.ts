import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import {
  ArtesanosService,
  CategoriasService,
  CreateProductPayload,
  ProductosService
} from '../../../servicios';

@Component({
  selector: 'app-producto-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, CurrencyPipe],
  templateUrl: './producto-form.component.html',
  styleUrls: ['./producto-form.component.css']
})
export class ProductoFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly categoriasService = inject(CategoriasService);
  private readonly artesanosService = inject(ArtesanosService);
  private readonly productosService = inject(ProductosService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly categorias = signal<{ slug: string; name: string }[]>([]);
  protected readonly artesanos = signal<{ workshop_name: string }[]>([]);
  protected readonly cargando = signal(true);
  protected readonly procesando = signal(false);
  protected readonly mensaje = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);

  protected readonly formulario = this.fb.group({
    sku: ['', [Validators.required, Validators.maxLength(30)]],
    name: ['', [Validators.required, Validators.maxLength(150)]],
    category_slug: ['', Validators.required],
    artisan_workshop: ['', Validators.required],
    price: [0, [Validators.required, Validators.min(0)]],
    stock: [0, [Validators.required, Validators.min(0)]],
    badge_label: [''],
    description: [''],
    image_url: ['']
  });

  protected readonly resumenPrecio = computed(() => this.formulario.controls.price.value ?? 0);

  ngOnInit(): void {
    this.cargarDatosIniciales();
  }

  private cargarDatosIniciales(): void {
    this.cargando.set(true);
    this.error.set(null);

    this.categoriasService
      .obtenerCategorias()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => {
          this.categorias.set((resp.data ?? []).map((cat) => ({ slug: cat.slug, name: cat.name })));
        },
        error: (err) => {
          console.error(err);
          this.error.set('No pudimos cargar las categorías.');
        }
      });

    this.artesanosService
      .obtenerArtesanos()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => {
          this.artesanos.set((resp.data ?? []).map((art) => ({ workshop_name: art.workshop_name })));
          this.cargando.set(false);
        },
        error: (err) => {
          console.error(err);
          this.error.set('No pudimos cargar los artesanos.');
          this.cargando.set(false);
        }
      });
  }

  guardar(): void {
    if (this.formulario.invalid) {
      this.formulario.markAllAsTouched();
      return;
    }

    this.procesando.set(true);
    this.mensaje.set(null);
    this.error.set(null);

    const datos = this.formulario.getRawValue();
    const payload: CreateProductPayload = {
      sku: datos.sku!.trim().toUpperCase(),
      name: datos.name!.trim(),
      category_slug: datos.category_slug!,
      artisan_workshop: datos.artisan_workshop!,
      price: Number(datos.price),
      stock: Number(datos.stock),
      badge_label: datos.badge_label?.trim() || null,
      description: datos.description?.trim() || null,
      image_url: datos.image_url?.trim() || null
    };

    this.productosService
      .registrarProducto(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => {
          this.procesando.set(false);
          this.mensaje.set(`Producto ${resp.data?.name ?? payload.name} guardado correctamente.`);
          this.formulario.reset({
            sku: '',
            name: '',
            category_slug: '',
            artisan_workshop: '',
            price: 0,
            stock: 0,
            badge_label: '',
            description: '',
            image_url: ''
          });
        },
        error: (err) => {
          console.error('Error al guardar producto', err);
          const detalle = err?.error?.detail || err?.error?.error || 'No pudimos registrar el producto.';
          this.error.set(detalle);
          this.procesando.set(false);
        }
      });
  }
}
