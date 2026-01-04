import { Routes } from '@angular/router';

import { CartComponent } from './pages/cart/cart.component';
import { CheckoutComponent } from './pages/checkout/checkout.component';
import { HomeComponent } from './pages/home/home.component';
import { ProductoFormComponent } from './pages/admin/producto/producto-form.component';
import { ResumenVentasComponent } from './pages/admin/resumen-ventas/resumen-ventas.component';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'cart', component: CartComponent },
  { path: 'checkout', component: CheckoutComponent },
  { path: 'admin/productos', component: ProductoFormComponent },
  { path: 'admin/resumen', component: ResumenVentasComponent },
  { path: '**', redirectTo: '' }
];
