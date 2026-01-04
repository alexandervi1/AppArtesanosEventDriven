import { Injectable, computed, signal } from '@angular/core';

import { ProductListItem } from './api-types';

export interface CartItem {
  productId: number;
  sku: string;
  name: string;
  price: number;
  quantity: number;
  imageUrl: string | null;
}

@Injectable({
  providedIn: 'root'
})
export class CartStateService {
  private readonly itemsSignal = signal<CartItem[]>([]);

  readonly items = computed(() => this.itemsSignal());
  readonly totalItems = computed(() => this.itemsSignal().reduce((sum, item) => sum + item.quantity, 0));
  readonly totalAmount = computed(() => this.itemsSignal().reduce((sum, item) => sum + item.price * item.quantity, 0));

  addItem(product: ProductListItem, quantity = 1): void {
    if (quantity <= 0) return;
    this.itemsSignal.update((items) => {
      const existing = items.find((i) => i.productId === product.product_id);
      if (existing) {
        return items.map((item) =>
          item.productId === product.product_id
            ? { ...item, quantity: Math.min(item.quantity + quantity, 99) }
            : item
        );
      }
      return [
        ...items,
        {
          productId: product.product_id,
          sku: product.sku,
          name: product.name,
          price: product.price,
          quantity: Math.min(quantity, 99),
          imageUrl: product.image_url ?? null
        }
      ];
    });
  }

  updateQuantity(productId: number, quantity: number): void {
    if (quantity <= 0) {
      this.removeItem(productId);
      return;
    }
    this.itemsSignal.update((items) =>
      items.map((item) =>
        item.productId === productId ? { ...item, quantity: Math.min(quantity, 99) } : item
      )
    );
  }

  removeItem(productId: number): void {
    this.itemsSignal.update((items) => items.filter((item) => item.productId !== productId));
  }

  clear(): void {
    this.itemsSignal.set([]);
  }
}
