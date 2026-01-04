import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  concatMap,
  debounceTime,
  distinctUntilChanged,
  finalize,
  from,
  map,
  of,
  switchMap,
  tap,
  toArray
} from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import {
  CarritosService,
  CartStateService,
  ClientesService,
  Customer,
  CustomerPayload,
  OrderDetail,
  PedidosService
} from '../../servicios';

@Component({
  selector: 'app-checkout',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, CurrencyPipe],
  templateUrl: './checkout.component.html',
  styleUrls: ['./checkout.component.css']
})
export class CheckoutComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly cartState = inject(CartStateService);
  private readonly clientesService = inject(ClientesService);
  private readonly carritosService = inject(CarritosService);
  private readonly pedidosService = inject(PedidosService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly items = computed(() => this.cartState.items());
  protected readonly total = computed(() => this.cartState.totalAmount());

  protected readonly procesando = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly orden = signal<OrderDetail | null>(null);
  private readonly clientes = signal<Customer[]>([]);
  protected readonly emailSuggestions = signal<Customer[]>([]);
  protected readonly emailFocused = signal(false);
  private blurTimeout: ReturnType<typeof setTimeout> | null = null;

  protected readonly formulario = this.fb.nonNullable.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
    province: [''],
    city: [''],
    address_line: [''],
    notes: ['']
  });

  ngOnInit(): void {
    if (this.items().length === 0) {
      this.router.navigate(['/cart']);
    }

    this.clientesService
      .listarClientes({ limit: 200 })
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        map((resp) => resp.data ?? []),
        tap((clientes) => this.clientes.set(clientes))
      )
      .subscribe();

    const emailControl = this.formulario.controls.email;
    emailControl.valueChanges
      .pipe(
        debounceTime(200),
        distinctUntilChanged(),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((valor) => this.actualizarSugerencias(valor ?? ''));

    this.destroyRef.onDestroy(() => {
      if (this.blurTimeout) {
        clearTimeout(this.blurTimeout);
      }
    });
  }

  volverAlInicio(): void {
    this.router.navigate(['/']);
  }

  proceder(): void {
    if (this.formulario.invalid) {
      this.formulario.markAllAsTouched();
      return;
    }
    if (!this.items().length) {
      this.error.set('Tu carrito esta vacio.');
      return;
    }

    this.procesando.set(true);
    this.error.set(null);
    const datos = this.formulario.getRawValue();
    const items = [...this.items()];

    const clientePayload: CustomerPayload = {
      first_name: datos.first_name,
      last_name: datos.last_name,
      email: datos.email,
      phone: datos.phone || null,
      province: datos.province || null,
      city: datos.city || null,
      address_line: datos.address_line || null
    };

    this.clientesService
      .buscarPorEmail(datos.email)
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        map((resp) => resp.data || null),
        switchMap((cliente) => {
          if (cliente) return of(cliente);
          return this.clientesService
            .crearCliente({
              ...clientePayload,
              phone: clientePayload.phone ?? undefined,
              province: clientePayload.province ?? undefined,
              city: clientePayload.city ?? undefined,
              address_line: clientePayload.address_line ?? undefined
            })
            .pipe(map((resp) => resp.data as Customer));
        }),
        switchMap((cliente) => {
          this.agregarClienteACache(cliente);
          return this.carritosService.crearCarrito({ customer_id: cliente.customer_id }).pipe(
            map((resp) => ({ cliente, carrito: resp.data }))
          );
        }),
        switchMap(({ cliente, carrito }) =>
          from(items).pipe(
            concatMap((item) =>
              this.carritosService.agregarItem({
                cart_id: carrito.cart_id,
                product_id: item.productId,
                quantity: item.quantity
              })
            ),
            toArray(),
            map(() => ({ cliente, carrito }))
          )
        ),
        switchMap(({ carrito }) =>
          this.pedidosService
            .crearPedido({
              cart_id: carrito.cart_id,
              currency: 'USD',
              status: 'paid',
              payment_status: 'paid',
              tax: 0,
              shipping_cost: 0,
              notes: datos.notes || undefined
            })
            .pipe(map((resp) => resp.data))
        ),
        finalize(() => this.procesando.set(false))
      )
      .subscribe({
        next: (orden) => {
          this.orden.set(orden as OrderDetail);
          this.cartState.clear();
        },
        error: (err) => {
          console.error('Error procesando el pago', err);
          const mensaje =
            err?.error?.detail ||
            err?.error?.error ||
            'No pudimos completar la compra. Revisa los datos o intenta mas tarde.';
          this.error.set(mensaje);
        }
      });
  }

  protected onEmailFocus(): void {
    if (this.blurTimeout) {
      clearTimeout(this.blurTimeout);
      this.blurTimeout = null;
    }
    this.emailFocused.set(true);
    this.actualizarSugerencias(this.formulario.controls.email.value ?? '');
  }

  protected onEmailBlur(): void {
    this.blurTimeout = setTimeout(() => {
      this.emailFocused.set(false);
      this.emailSuggestions.set([]);
    }, 150);
  }

  protected seleccionarCorreo(cliente: Customer): void {
    this.formulario.patchValue(
      {
        email: cliente.email,
        first_name: cliente.first_name || '',
        last_name: cliente.last_name || '',
        phone: cliente.phone || '',
        province: cliente.province || '',
        city: cliente.city || '',
        address_line: cliente.address_line || ''
      },
      { emitEvent: false }
    );
    this.emailSuggestions.set([]);
    this.emailFocused.set(false);
  }

  private actualizarSugerencias(valor: string): void {
    const termino = valor.trim().toLowerCase();
    if (!termino) {
      this.emailSuggestions.set([]);
      return;
    }

    const coincidencias = this.clientes()
      .filter((cliente) => cliente.email.toLowerCase().includes(termino))
      .slice(0, 5);

    this.emailSuggestions.set(coincidencias);

    const coincidenciaExacta = coincidencias.find(
      (cliente) => cliente.email.toLowerCase() === termino
    );
    if (coincidenciaExacta) {
      this.rellenarDesdeCliente(coincidenciaExacta);
    }
  }

  private rellenarDesdeCliente(cliente: Customer): void {
    this.formulario.patchValue(
      {
        first_name: cliente.first_name || '',
        last_name: cliente.last_name || '',
        phone: cliente.phone || '',
        province: cliente.province || '',
        city: cliente.city || '',
        address_line: cliente.address_line || ''
      },
      { emitEvent: false }
    );
  }

  private agregarClienteACache(cliente: Customer): void {
    this.clientes.update((lista) => {
      const existente = lista.find((item) => item.customer_id === cliente.customer_id);
      if (existente) {
        return lista.map((item) => (item.customer_id === cliente.customer_id ? cliente : item));
      }
      return [cliente, ...lista].slice(0, 200);
    });
  }
}

