# تقرير المرحلة الأولى: شبكة البريد التجريبية

هذه محاكاة تعليمية مستقلة وليست تكاملاً رسمياً مع البريد اليمني. أسماء المناطق والمكاتب والعناوين بيانات تجريبية ولا تثبت وجود فروع رسمية أو تقديمها خدمة التحصيل الفعلية.

## ما تم تجهيزه

- جدول `postal_offices`: الاسم، الرمز الفريد، المنطقة، العنوان، الحالة، `is_demo` والتواريخ.
- جدول `postal_collectors`: حسابات مستقلة، ارتباط إلزامي بالمكتب، اسم مستخدم فريد، كلمة مرور مجزأة، حالة الحساب، صلاحية `can_collect` وإصدار الجلسة `auth_version`. يمنع المفتاح الأجنبي حذف مكتب مرتبط بحسابات.
- حارس ومزوّد مصادقة `postal` مستقلان عن `web/users`. لم تُضف أدوار إلى المستخدمين الحاليين ولم تُعدّل منح مراجعة الخدمات.
- صلاحية `can_collect` تفتح بوابة البريد التجريبية فقط. لا توجد عمليات تحصيل أو فواتير أو سندات أو اتصالات Mockoon في هذه المرحلة.
- إدارة عربية RTL لإضافة وتعديل وتعطيل المكاتب والحسابات؛ كلمات المرور تُدخل يدوياً عند إنشاء الحسابات من الإدارة أو تأتي من البيئة للـSeeder. لا تظهر كلمات المرور الحالية.
- إبطال جلسات المحصل عند تغيير كلمة المرور أو اسم المستخدم أو المكتب أو حالة الحساب أو صلاحيته. تعطيل المكتب وإعادة تفعيله لا يعيدان صلاحية الجلسات القديمة.
- حماية CSRF، تحديد معدل محاولات الدخول، منع تخزين صفحات البريد في ذاكرة المتصفح، وتسجيل عمليات الإدارة في سجل التدقيق الحالي دون تخزين كلمات المرور.

## المكاتب والحسابات التي ينشئها Seeder

| الرمز | الاسم التجريبي | اسم المستخدم المستقل | متغير كلمة المرور |
| --- | --- | --- | --- |
| SANA-001 | مكتب البريد المركزي – صنعاء | postal_central_demo | POSTAL_DEMO_CENTRAL_PASSWORD |
| SANA-002 | مكتب بريد التحرير | postal_tahrir_demo | POSTAL_DEMO_TAHRIR_PASSWORD |
| SANA-003 | مكتب بريد حدة – فرع تجريبي | postal_hadda_demo | POSTAL_DEMO_HADDA_PASSWORD |

كلمات المرور غير محددة في المستودع: يجب توليد كلمة مختلفة آمنة لكل حساب، بطول 12 محرفاً على الأقل، مع أحرف إنجليزية كبيرة وصغيرة وأرقام ورموز، وبحد أقصى 72 بايت لتجنب اقتطاع bcrypt. تخزن قاعدة البيانات التجزئة فقط. بعد إنشاء الحسابات يمكن إزالة متغيرات كلمات المرور من بيئة التشغيل؛ التكرار لا يحتاجها للحسابات الموجودة ولا يعيد ضبطها.

## الفحص وحدود التحقق

فُحصت `User` وأدواره الثلاثة و`ServiceReviewPermission` ووسائط التحقق من الحالة والدور وإدارة الموظفين والمصادقة ومستمعو تدقيق الدخول، إضافة إلى migrations. لم يُعثر على جداول بريد مناسبة في ملفات المشروع.

جرت محاولة اتصال قراءة فقط بقاعدة MySQL قبل إنشاء migration. الاتصال رفض برمز 2002؛ لذلك لم يمكن إثبات الجداول المطبقة فعلياً أو إصدار خادم MySQL. لم يُشغّل migration أو Seeder على قاعدة المشروع. اختبارات التنفيذ تعمل حصراً على SQLite بالذاكرة، ولا تثبت التوافق التشغيلي مع خادم MySQL الفعلي حتى يُتاح فحصه واختبار نسخة معزولة منه.

الـmigration يفحص اسمي الجدولين قبل إنشاء أي منهما، ويرفض وجود جدول سابق بدلاً من تبنيه أو تعديله. يجب مع ذلك فحص schema ومراجعة migrations المعلقة قبل الموافقة على التطبيق الفعلي. في MySQL قد يترك فشل إنشاء الجدول الثاني جدولاً أول بسبب طبيعة DDL؛ يلزم فحص الحالة يدوياً عند أي إخفاق، دون حذف تلقائي.

## التفعيل لاحقاً، بعد الموافقة على قاعدة البيانات

الميزة معطلة افتراضياً ولا تتاح إلا في بيئة `local` أو `testing`. عند تعطيلها تختفي وصلة الإدارة وتعيد مساراتها 404 دون الحاجة لوجود الجدولين. التعطيل لا يحذف البيانات؛ الحسابات الحكومية الحالية تواصل العمل.

لم تُعدّل ملفات البيئة. الإعدادات المطلوبة يحددها المشغّل عبر بيئة التشغيل:

- `POSTAL_NETWORK_ENABLED=true` لإظهار الميزة.
- `POSTAL_NETWORK_ALLOW_SEED=true` للسماح الصريح بإدخال الأمثلة.
- المتغيرات الثلاثة لكلمات المرور الموضحة أعلاه، دون وضع قيمها في المستودع أو الأوامر المسجلة أو التقرير.

الأوامر التالية توثيق فقط، **لم تُنفّذ على MySQL**. لا تُنفّذ إلا بعد الموافقة الصريحة وإعادة فحص القاعدة ونسخة احتياطية مناسبة:

```powershell
php artisan migrate --path=database/migrations/2026_11_11_000002_create_postal_network_tables.php
php artisan db:seed --class=PostalNetworkSeeder
```

لم يُربط الـSeeder بـ`DatabaseSeeder`؛ تشغيل seed عام لا ينشئ حسابات البريد. يتحقق من جميع كلمات المرور المطلوبة قبل الإدخال ويستخدم معاملة واحدة، ويرفض تعارض الحسابات الموجودة. التكرار يعتمد على الرمز و`fixture_key` ويحافظ على كلمات المرور والتعديلات والتعطيل وإسناد المكاتب القائم. يفضل إيقاف `POSTAL_NETWORK_ALLOW_SEED` بعد التهيئة. عند استخدام config cache، تُحدّث الإعدادات المخبأة بالطريقة التشغيلية المعتادة بعد تغيير بيئة التشغيل.

المسارات:

- `/admin/postal-network`: إدارة المكاتب والحسابات، للمدير الحكومي الحالي فقط.
- `/postal/login`: دخول محصل البريد المستقل.
- `/postal`: عرض بيانات مكتب المحصل فقط.

يمكن إنشاء الحسابات يدوياً من الإدارة بدلاً من Seeder، بكلمات مرور يحددها المدير دون تخزين قيمها في الملفات. الدخول البريدي لا يتيح مراجعة المواطنين أو تعديل الرسوم أو إصدار البطاقات. تسجيل الخروج البريدي يحافظ على جلسة الحكومة عند وجودها في المتصفح نفسه.

## الملفات الجديدة

- `database/migrations/2026_11_11_000002_create_postal_network_tables.php`
- `database/seeders/PostalNetworkSeeder.php`
- `app/Models/PostalOffice.php`
- `app/Models/PostalCollector.php`
- `config/postal_network.php`
- `app/Rules/PostalPasswordLength.php`
- `app/Http/Middleware/EnsurePostalNetworkEnabled.php`
- `app/Http/Middleware/AuthenticatePostalCollector.php`
- `app/Http/Controllers/PostalNetworkController.php`
- `app/Http/Controllers/PostalAuthController.php`
- `resources/views/admin/postal-network.blade.php`
- `resources/views/admin/postal-network-office-fields.blade.php`
- `resources/views/admin/postal-network-collector-fields.blade.php`
- `resources/views/postal/layout.blade.php`
- `resources/views/postal/login.blade.php`
- `resources/views/postal/home.blade.php`
- `public/css/postal-network.css`
- `tests/Feature/PostalNetworkTest.php`
- `docs/superpowers/plans/2026-11-11-postal-network.md`
- هذا التقرير.

## الملفات الحالية المعدلة لهذه المرحلة

- `config/auth.php`: إضافة حارس ومزوّد البريد دون تغيير الافتراضي.
- `app/Providers/AppServiceProvider.php`: حصر تدقيق مصادقة الحكومة في حارس web حتى لا تُعامل هوية البريد كـUser حكومي.
- `routes/web.php`: مسارات الإدارة وبوابة البريد المحمية.
- `resources/views/components/operations-sidebar.blade.php`: رابط الإدارة عند تفعيل المحاكاة.

توجد تعديلات سابقة للسجل المدني التجريبي في مساحة العمل؛ لم تُحذف ولم تُنسب إلى مرحلة البريد.

## الاختبارات

`tests/Feature/PostalNetworkTest.php` يغطي: تعطيل الميزة حتى دون جداولها، قصرها على بيئة التجربة، العلاقة بين المحصل والمكتب، منع التكرار بقيود قاعدة البيانات والتحقق، منع حذف مكتب مستخدم، تكرار Seeder دون تعديل المستخدمين أو منح الحكومة، غياب كلمات المرور وتعارض الحسابات دون إدخال جزئي، منع كلمات المرور الضعيفة أو الطويلة بالبايت، حصر الإدارة بالمدير، عزل الدخول بين الحارسين، إبطال الجلسات، تحديد محاولات الدخول، وحفظ جلسة وتدقيق الحكومة عند استخدام البريد.

أمر اختبارات الذاكرة (لا يستخدم MySQL):

```powershell
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP 'e-issuance-postal-isolated-config.php'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/PostalNetworkTest.php
```

نتائج التحقق:

- 21 اختباراً جديداً للبريد ضمن مجموعة موجّهة تشمل كذلك `WorkspaceIsolationTest` و`WorkspaceExperienceTest` و`DemoCivilRegistryTest`: نجحت 54 حالة و1109 تحققاً.
- المجموعة الكاملة: 492 اختباراً و5421 تحققاً؛ 5 إخفاقات واختبار واحد متجاوز. الإخفاقات تطابق المشكلات السابقة خارج مرحلة البريد:
  - `CivilRegistryGuidesTest::test_each_image_has_its_own_page_with_all_transcribed_sections`
  - `CivilRegistryGuidesTest::test_guides_live_in_identity_services_without_duplicating_them_on_home`
  - `EmployeeReportingTest::test_empty_real_reports_and_csv_are_available_to_admin` (غياب نص تعريف المؤشرات).
  - `UserAuthenticationTest::test_citizen_registration_hashes_password_and_preserves_session_login`
  - `UserAuthenticationTest::test_registration_rejects_duplicate_email_and_national_id`
  - إخفاقا التسجيل يعرضان شرط إدخال الاسم الرباعي. لم تُعدّل هذه الوظائف أو اختباراتها ضمن هذه المرحلة.
- فحص Pint لملفات PHP الجديدة: ناجح. فحص صياغة PHP للملفات الجديدة والحالية المعدلة: ناجح. فحص `git diff --check`: ناجح.
- لم يُجر اختبار بصري يدوي بالمتصفح أو اختبار MySQL تشغيلي، ولم تُنشأ الحسابات داخل قاعدة المشروع الفعلية.

## المرحلة التالية

لم يُنفّذ نظام الفواتير والتحصيل أو Mockoon. ينتظر ذلك موافقة مستقلة بعد مراجعة هذه المرحلة.
