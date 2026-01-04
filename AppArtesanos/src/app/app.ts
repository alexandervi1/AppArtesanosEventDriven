import { Component, computed, inject } from '@angular/core';
import { NgIf } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { CartStateService } from './servicios';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NgIf],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
  private readonly cartState = inject(CartStateService);
  protected readonly totalItems = computed(() => this.cartState.totalItems());
  protected readonly year = new Date().getFullYear();
}
