# Controllers Catalog

Dokumentasi singkat untuk mengelompokkan controller berdasarkan domain fitur. File ini hanya untuk referensi pengembang; tidak memengaruhi routing.

## Attendance
- AttendanceController (clock, logs)
- AttendanceGeofencesController (CRUD geofences)
- AttendanceShiftsController (CRUD shifts)
- AttendanceSchedulesController (jadwal/shift harian)
- LeaveTypesController (master jenis cuti)
- LeaveRequestsController (pengajuan cuti)
- OvertimeRequestsController (lembur)

## Sales
- SalesOrderController (orders & actions)
- SalesCustomersController (customers)
- SalesPropertiesController (properties)
- SalesUnitsController (units)
- SalesListingsController (listings)
- ContractsController (contracts)
- InvoicesController (invoices)
- PaymentsController (payments)
- PublicSalesController (listings publik)

## Core
- AuthController (login/refresh/logout)
- MeController (profil & permissions user aktif)
- UsersController (manajemen user)
- RolesController (role & permission role)
- ModulesController (module)
- FeaturesController (feature)
- PermissionsMasterController (daftar permission master)
- AuditLogsController (audit trail)
- HomeController (landing API)

## Menu
- MenuController (menu publik/aware user)
- MenuAdminController (admin menu & items)

Catatan:
- Rekomendasi struktur namespace ke depan: pisahkan file ke sub-folder `Attendance/`, `Sales/`, `Core/`, `Menu/` untuk menjaga keteraturan. Jika perubahan ini dilakukan, pastikan memperbarui import di `routes/api.php`.
