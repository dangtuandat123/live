## 2026-05-22T04:51:14Z

We need to fix the Admin Dashboard and Admin Users Page in the livestream analysis application (d:\Workspace\livestream).
Please perform the following changes in the directory d:\Workspace\livestream:
1. In `backend/routes/web.php` (Admin Area, route `admin.dashboard` and `admin.users.index`):
   - Replace total revenue `$revenueVal` with the actual sum of amounts for all transactions with 'success' status: `Transaction::where('status', 'success')->sum('amount')`.
   - Update `$revenueData` in the monthly loop to calculate the sum of amounts of successful transactions created in that specific month and year, instead of mock user counts multiplication.
   - For `recentUsers` query mapping, replace the mock plan assignment (`$u->id % 3` logic) with the user's actual active subscription package name: `$activeSub = $u->resolveActiveSubscription(); $plan = $activeSub && $activeSub->package ? $activeSub->package->name : 'Free';`.
   - In the stats array, change the title from 'Doanh thu ước tính' to 'Tổng doanh thu', and display the formatted total revenue (e.g. `number_format($totalRevenueVal) . 'đ'`).
   - For route `admin.users.index`, eager load the `activeSubscription.package` relationship when retrieving users. Map the collection to resolve each user's active subscription and assign `$u->plan_name = $activeSub && $activeSub->package ? $activeSub->package->name : 'Free';` so it is passed to frontend Inertia render.
2. In `backend/resources/js/types/index.d.ts`:
   - In interface `User`, add `plan_name?: string;` property to prevent typescript compilation errors.
3. In `backend/resources/js/Pages/Admin/Users/Index.tsx`:
   - Add a `<TableHead>Gói</TableHead>` column header to the user table (e.g., between "Vai trò" and "Xác thực email").
   - Add a corresponding `<TableCell>` displaying the user's plan badge:
     ```typescript
     <TableCell>
         <Badge
             variant={
                 user.plan_name === 'Enterprise'
                     ? 'default'
                     : user.plan_name === 'Pro'
                       ? 'secondary'
                       : 'outline'
             }
         >
             {user.plan_name || 'Free'}
         </Badge>
     </TableCell>
     ```
4. Run `npm run build` and `php artisan test` in `backend/` to verify that assets build succeeds with zero errors and all backend tests pass cleanly.
