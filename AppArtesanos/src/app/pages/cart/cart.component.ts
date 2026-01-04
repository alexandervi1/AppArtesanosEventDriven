import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { CartItem, CartStateService } from '../../servicios';

@Component({
  selector: 'app-cart',
  standalone: true,
  imports: [CommonModule, RouterLink, CurrencyPipe],
  templateUrl: './cart.component.html',
  styleUrls: ['./cart.component.css']
})
export class CartComponent {
  private readonly cartState = inject(CartStateService);

  protected readonly items = computed(() => this.cartState.items());
  protected readonly totalItems = computed(() => this.cartState.totalItems());
  protected readonly totalAmount = computed(() => this.cartState.totalAmount());

  incrementar(item: CartItem): void {
    this.cartState.updateQuantity(item.productId, item.quantity + 1);
  }

  disminuir(item: CartItem): void {
    this.cartState.updateQuantity(item.productId, item.quantity - 1);
  }

  actualizarCantidad(item: CartItem, valor: string): void {
    const cantidad = Number.parseInt(valor, 10);
    if (Number.isNaN(cantidad)) return;
    this.cartState.updateQuantity(item.productId, cantidad);
  }

  eliminar(item: CartItem): void {
    this.cartState.removeItem(item.productId);
  }
}
