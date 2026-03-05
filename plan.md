0) بروموت تأسيس المشروع (Laravel)

بروموت:

أنشئ مشروع Laravel جديد مع لوحة تحكم Admin جاهزة (قالب جاهز).
فعّل: Authentication (تسجيل دخول)، Middleware للحماية، Layout موحد (Sidebar + Topbar).
أنشئ إعدادات عامة للتطبيق (AppSettings) لحفظ: اسم الشركة، العملة الافتراضية، سعر الصرف الحالي (اختياري)، شعار.
أنشئ صفحة Dashboard فارغة حالياً.
حضّر Routes منظمة (web.php) مع prefix = /admin واسماء routes.
استخدم MySQL.
أنشئ Seed مستخدم Admin افتراضي.

اختبار:

تسجيل دخول/خروج

دخول لوحة التحكم

1) مرحلة إدارة المستخدمين والصلاحيات

بروموت:

أنشئ نظام Users داخل لوحة التحكم:
CRUD للمستخدمين (name, phone, email اختياري, password).
أضف Roles بسيطة: (admin, staff).
اربط صلاحيات بالـ Middleware أو Gate بحيث: admin فقط يقدر يدير المستخدمين، staff لا.
أضف سجل “created_by / updated_by” في الجداول المناسبة عبر Global scope أو Observers.

اختبار:

admin يضيف/يعدل/يحذف مستخدم

staff ما يقدر يدخل صفحة Users

2) مرحلة العملاء (Customers) + ديونهم

بروموت:

أنشئ Customers داخل لوحة التحكم:
CRUD للعميل: (name, phone, address اختياري, notes اختياري, opening_balance = رصيد افتتاحي).
أنشئ صفحة “كشف حساب العميل” تعرض الحركات (ledger) وترصيد إجمالي.
جهّز جدول customer_ledgers:
(id, customer_id, debit, credit, currency, exchange_rate, ref_type, ref_id, note, created_at, created_by).
عند إنشاء عميل برصيد افتتاحي > 0: اكتب حركة Debit في ledger بعنوان “رصيد افتتاحي”.

اختبار:

إنشاء عميل

ظهور الرصيد الافتتاحي بكشف الحساب

3) مرحلة المنتجات والأقسام والمخزون

بروموت:

أنشئ إدارة Products + Categories:
Categories CRUD: (name).
Products CRUD: (name, sku/barcode, category_id, cost, price, unit, stock_alert, is_active).
أضف صفحة “حركة مخزون” للمنتج.
أنشئ جدول stock_movements:
(id, product_id, qty_in, qty_out, ref_type, ref_id, note, created_at, created_by).
أنشئ رصيد المنتج المحسوب = SUM(qty_in - qty_out).
أضف “رصيد افتتاحي” للمنتج: حقل optional في شاشة إنشاء/تعديل المنتج، وإذا أدخل المستخدم كمية افتتاحية، أنشئ StockMovement qty_in لها كـ ref_type = opening.

اختبار:

إضافة منتج برصيد افتتاحي

الرصيد يظهر صحيح

صفحة حركات المخزون تعرض السجلات

4) مرحلة فواتير البيع (Sales Invoices)

بروموت:

أنشئ نظام فواتير البيع:
جدول invoices: (id, type='sale', customer_id, invoice_no, date, currency, exchange_rate, subtotal, discount, total, paid_amount, remaining_amount, status='draft/confirmed/canceled', notes, created_by).
جدول invoice_items: (id, invoice_id, product_id, qty, price, cost_snapshot, total_line).
واجهة إنشاء فاتورة: اختيار عميل + تاريخ + عملة + سعر صرف + إضافة أصناف (بحث بالاسم/الباركود) + كميات + أسعار.
عند الحفظ كـ Draft: لا تؤثر على المخزون.
عند Confirm:

أنشئ stock_movements qty_out لكل صنف ref_type='invoice' ref_id=invoice_id

احسب totals وخزنها

إذا العميل آجل (remaining_amount > 0): اكتب حركة Debit في customer_ledgers بقيمة remaining_amount ref_type='invoice'
أضف صفحة عرض/طباعة الفاتورة HTML.

اختبار:

Draft لا يغير المخزون

Confirm ينقص المخزون

يظهر دين على العميل في كشف الحساب

5) مرحلة سندات القبض (Payments) لتسديد الديون

بروموت:

أنشئ Payments CRUD:
جدول payments: (id, customer_id, date, amount, currency, exchange_rate, note, created_by).
عند إنشاء Payment:

اكتب حركة Credit في customer_ledgers بقيمة amount ref_type='payment' ref_id=payment_id
أضف صفحة “سند قبض” للطباعة.
أضف في صفحة العميل: زر “إضافة دفعة” سريع.

اختبار:

إضافة دفعة تقلل رصيد العميل في كشف الحساب

6) مرحلة فواتير الشراء (Purchases) + الموردين

بروموت:

أنشئ Suppliers CRUD: (name, phone, address, notes, opening_balance optional).
أنشئ invoices type='purchase' مع supplier_id بدل customer_id (أو جدول منفصل إذا تريد).
عند Confirm purchase:

أنشئ stock_movements qty_in لكل صنف

(اختياري) إذا يوجد ديون للمورد، أنشئ supplier_ledgers مشابه للعميل

اختبار:

شراء مؤكّد يزيد المخزون

7) مرحلة سعر الصرف والتحكم به

بروموت:

أنشئ صفحة “سعر الصرف” ضمن Settings:
جدول exchange_rates أو AppSettings يخزن السعر الحالي + سجل تغييرات.
المهم: كل فاتورة/دفعة تخزن exchange_rate الخاص بها (snapshot) ولا تعتمد على سعر اليوم.
في التقارير: وفر خيار عرض النتائج بعملة النظام أو عملة الفاتورة.

اختبار:

تغيير سعر الصرف لا يغيّر فواتير قديمة

8) مرحلة التقارير (أسبوعي/شهري/سنوي)

بروموت:

أنشئ Reports داخل لوحة التحكم:

تقرير مبيعات: فلترة من تاريخ إلى تاريخ + إجمالي مبيعات + خصم + صافي + عدد فواتير.

تقرير مشتريات: نفس الفكرة.

تقرير الأرباح: يعتمد على (price - cost_snapshot) * qty لفواتير البيع المؤكدة.

تقرير العملاء (الديون): أعلى العملاء مديونية + إجمالي الديون.

تقرير المخزون: المنتجات النافدة + الأقل من stock_alert.
وفر أزرار تصدير PDF/Excel.

اختبار:

التقارير تطلع أرقام منطقية من بيانات تجريبية

9) مرحلة التدقيق والأمان والنشر

بروموت:

أضف Audit Log: جدول activity_logs يسجل (user_id, action, model, model_id, old_values, new_values, created_at).
أضف Validation قوي لكل النماذج.
أضف منع حذف الفواتير المؤكدة (فقط إلغاء Cancel).
جهّز Deployment على السيرفر: .env, migrations, storage link, queue optional.
أضف نسخة Backup يومية (اختياري).

اختبار:

إلغاء فاتورة يعكس تأثيرها (حسب منطقك)

لا حذف لفاتورة confirmed