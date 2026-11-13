# السجل المدني التجريبي — التنفيذ والتشغيل المعزول

## الحالة

الوحدة مدمجة في مشروع Laravel الحالي، وجرى استكمال التنفيذ السابق وفحصه على SQLite في الذاكرة. **لم تُشغّل migration أو Seeder على MySQL أو قاعدة المشروع الفعلية، ولم يُعدّل `.env`.** الوحدة مغلقة افتراضياً، وسياسة الوصول تقصرها على `local` و`testing` حتى عند تفعيل المفتاح في بيئة أخرى.

النافذة تستخدم `<dialog>` غير حاجب للصفحة وPointer Events للسحب بالماوس واللمس، وتدعم التصغير والإغلاق وEscape وإعادة التركيز. البحث والأقسام تستخدم fetch دون إعادة تحميل نموذج القرار. تُهمل الاستجابات القديمة عند تغيير الملف أو القسم بسرعة. يمكن فتح ملفات أفراد الأسرة وعرض الوثائق المستقلة والمرتبطة بالوقائع.

لوحة الأدمن توفر البحث والملفات، وإضافة المواطنين وتعديلهم وأرشفتهم، وإدارة الأسر والعضويات والوقائع الثمانية، ورفع الصور والوثائق واستبدالها وأرشفتها، وعرض التدقيق. نماذج الوقائع تعرض حقولاً عربية واختيارات للأطراف؛ يحتفظ الخادم بالتحقق الكامل من البيانات.

## تصميم البيانات والعزل

لم تُستخدم جداول `mock_civil_*` القديمة لأنها تخدم مسار التسجيل والتفعيل. لم يتغير `LocalCivilRegistryProvider` أو التحقق الرسمي.

| الجدول الجديد | الغرض والعلاقات |
| --- | --- |
| `demo_civil_people` | المواطن الاصطناعي، بمعرف فريد `DEMO-CIV-000001`، بلا علاقة حساب مستخدم |
| `demo_civil_families` | الأسرة وربها من الأشخاص التجريبيين |
| `demo_civil_family_members` | عضوية الشخص وقرابته وحالته وتاريخ الارتباط؛ قيد فريد للأسرة والشخص |
| `demo_civil_records` | أنواع الوقائع الثمانية، الشخص الأساسي، الأسرة، والواقعة السابقة عند الحاجة |
| `demo_civil_record_parties` | أطراف الواقعة وأدوارهم؛ يجعل الزواج والطلاق ظاهرين في ملف الطرفين |
| `demo_civil_documents` | وثيقة لشخص، ويمكن ربطها بواقعة، مع الحجم والنوع وبصمة SHA-256 |
| `demo_civil_registry_audits` | أحداث الوصول والإدارة والرفض؛ روابط سياق للفاعل والطلب دون تعديلهما |

استخدام جدول وقائع موحد مقصود؛ يميز `record_type` المواليد والزواج والطلاق والوفيات والبطاقات والأسرة والتصحيحات والقيود. العلاقات الأساسية والأطراف مفاتيح أجنبية، والحقول الخاصة بكل نوع في `details` مع تحقق بحسب النوع.

الـSeeder ينشئ 100 شخص و20 أسرة ووقائع متنوعة ووثائق وصور SVG توضيحية محلية، ويعيد تجهيز fixtures بالمعرفات نفسها دون تكرار. أسماء الأشخاص اصطناعية. للآباء والأمهات الأكبر سناً بيانات آباء موصوفة صراحة بأنها خارج العينة، دون اختراع علاقة بالشخص نفسه. تواريخ الوقائع ليست مستقبلية، والوفاة بعد الإنجاب، والطلاق بعد الزواج، ورب الأسرة المتوفى يُستبدل بعضو نشط. إعادة التجهيز تعيد قيم fixtures؛ لا تستخدمها لحفظ تعديلات يدوية طويلة الأجل عليها.

## الصلاحيات والتخزين والتدقيق

- صلاحية القراءة مستقلة باسم `users.can_access_demo_civil_registry`، تديرها شاشة `/admin/review-permissions` بنموذج مستقل عن صلاحيات الخدمة. لا تمنحها `can_activate` أو `can_verify`، ولا تمنح صلاحيات خدمة أخرى. تحديث هذا العلم الإداري هو التغيير المقصود الوحيد على حساب الموظف؛ البحث والملفات لا تغير الحسابات أو الطلبات.
- الموظف المخول يقرأ جميع الأنواع الثمانية. مسارات إدارة البيانات تقبل الأدمن فقط. يجري التحقق بالخادم عبر middleware وPolicies وForm Requests، بما في ذلك استدعاء المسار مباشرة.
- عند تمرير `request_id`، يتحقق الخادم من صلاحية مراجعة ذلك الطلب ويسجله كسياق تدقيق، دون تغيير حالة الطلب أو بياناته المشفرة.
- القرص `demo-civil-registry` خاص ومستقل في `storage/app/demo-civil-registry`، دون رابط عام. تُقدم الملفات عبر endpoints محمية فقط، مع فحص المسار والحجم والبصمة. رفع الأدمن يقبل JPEG وPNG وPDF، والصور الشخصية JPEG وPNG فقط. SVG مقتصر على fixtures المحلية المعروفة.
- حد الصورة 4 MiB، وحد الوثيقة 5 MiB. الأسماء المرفوعة عشوائية. الملفات المشتركة للـfixtures لا تُحذف عند استبدال وثيقة واحدة. الوثائق الحساسة تستخدم `private, no-store` و`nosniff` وسياسة CSP مقيدة.
- البحث محدود إلى 20 نتيجة في الصفحة، والأقسام إلى 50 واقعة، مع حد لمعدل البحث. لا تُخزن عبارة البحث في التدقيق؛ تُخزن بيانات تقنية مجزأة بدلاً من عنوان IP ووكيل المتصفح الخام.
- الأرشفة تبقي أثر السجل. لا توجد endpoints لتعديل أحداث التدقيق أو حذفها.

## الاستخدام

بعد تجهيز **نسخة اختبار معزولة** وتسجيل الدخول بحساباتها الاختبارية، يفعل المشغّل `DEMO_CIVIL_REGISTRY_ENABLED=true` في بيئة تلك النسخة فقط. لا يلزم تفعيل `ALLOW_SEED` لقراءة البيانات بعد تجهيزها.

الأدمن: افتح `/admin/demo-civil-registry` أو رابط السجل في التنقل الإداري، وابحث بالاسم أو المعرف أو رقم الوثيقة. إدارة الملفات والأسر والوقائع والوثائق في أقسام الصفحة، والتدقيق في `/admin/demo-civil-registry/audits`.

الموظف: يمنحه الأدمن «صلاحية السجل المدني التجريبي» من صفحة الصلاحيات، مع صلاحية مراجعة الخدمة اللازمة لفتح طلبها. من صفحة مراجعة الطلب اضغط «فتح السجل المدني التجريبي». هذه منحة قراءة عامة داخل الوحدة؛ اقتراح القسم لا يقيد البحث.

اقتراح القسم يستخدم خريطة صريحة في `ApplicationReviewController::suggestedRegistrySection` تقرأ snapshot الخدمة ونوع الوثيقة والكود ونوع الطلب والعملية. القيم الحالية `identity` و`birth_certificate` تقترحان البطاقات والمواليد، وتوجد خرائط صريحة لبقية الأنواع. القيم غير المعروفة لا تمنح صلاحية ولا تستنتج تطابقاً بالاسم الحر. يستطيع الموظف اختيار أي قسم من أقسام ملف المواطن.

## تشغيل الـSeeder بأمان

الـSeeder يرفض أي بيئة غير `testing`، ويرفض MySQL، ويتطلب `allow_seed=true` وSQLite في الذاكرة أو ملفاً ينتهي اسمه بـ`_testing`. لا يعمل تلقائياً ضمن Seeder النظام الرئيسي، ولا يوجد زر يعيد تهيئة قاعدة المشروع.

التحقق الآلي الأبسط، من جذر المشروع:

```powershell
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --filter=DemoCivilRegistryTest
node --test tests/frontend/demo-civil-registry.test.cjs
```

اختبارات الوحدة الجديدة تضبط SQLite `:memory:` وتتحقق من المحرك واسم القاعدة **قبل** تشغيل migrations، وتستخدم `Storage::fake` للملفات.

لتجهيز نسخة اختبار مستمرة، استخدم جلسة PowerShell مخصصة داخل **نسخة المشروع الاختبارية**، وليس اتصال المشروع الفعلي. الأوامر التالية تعليمات فقط ولم تُشغل أثناء التنفيذ:

```powershell
# جميع المتغيرات لهذه الجلسة فقط؛ أغلقها بعد اختبار النسخة المعزولة.
$env:APP_ENV = 'testing'
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP 'demo-registry-absent-config.php'
$env:DB_CONNECTION = 'sqlite'
$env:DB_URL = ''
$env:DEMO_CIVIL_REGISTRY_ENABLED = 'true'
$env:DEMO_CIVIL_REGISTRY_ALLOW_SEED = 'true'
$demoDirectory = Join-Path (Get-Location) 'storage/app/demo-registry-testing'
New-Item -ItemType Directory -Path $demoDirectory -Force | Out-Null
$env:DB_DATABASE = Join-Path $demoDirectory 'civil_registry_testing'
if (!(Test-Path -LiteralPath $env:DB_DATABASE)) {
    New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null
}
php artisan migrate --env=testing
if ($LASTEXITCODE -ne 0) { throw 'Migration failed; do not seed.' }
php artisan db:seed --class=DemoCivilRegistrySeeder --env=testing
if ($LASTEXITCODE -ne 0) { throw 'Demo seed failed.' }
$env:DEMO_CIVIL_REGISTRY_ALLOW_SEED = 'false'
```

مسار `APP_CONFIG_CACHE` أعلاه يجب أن يكون غير موجود. لا تنقل إعدادات جلسة الاختبار إلى جلسة تشغيل MySQL. حسابات تسجيل الدخول للنسخة الاختبارية تُجهز وفق آلية المشروع المعتمدة؛ الـSeeder المدني لا ينشئ حسابات ولا ينسخ حسابات حقيقية. إعادة تشغيل أمر الـSeeder في القاعدة الاختبارية نفسها يعيد fixtures دون تكرار، مع تفعيل `ALLOW_SEED` مؤقتاً.

## ما استُكمل خلال هذه المراجعة

إصلاح علاقات الوالدين وتواريخ الوقائع وصيغ المعرفات والتواريخ، واستكمال الزواج للأسرة العشرين، وتصحيح رب الأسرة ووثيقته بعد الوفاة. إصلاح تعديل الطلاق المرتبط بزواج منتهٍ ومنع تغيير رب الأسرة عبر مسار العضوية. إظهار الوثائق غير المرتبطة بواقعة. إضافة تنقل أفراد الأسرة وإغلاق لوحة المفاتيح وحماية الواجهة من الاستجابات المتأخرة. إصلاح خطأ نحوي كان يمنع ملف بحث الأدمن من العمل. استبدال مدخلات JSON الظاهرة بحقول عربية عند توفر JavaScript، مع بقاء تحقق الخادم.

## ملفات التنفيذ

الملفات القائمة المعدلة: `routes/web.php`، `bootstrap/app.php`، `config/filesystems.php`، `app/Models/User.php`، `app/Policies/UserPolicy.php`، `app/Services/Workspace/EmployeeWorkspace.php`، `app/Http/Controllers/ApplicationReviewController.php`، `app/Http/Controllers/ReviewPermissionController.php`، `resources/views/admin/review-permissions.blade.php`، `resources/views/components/operations-sidebar.blade.php`، `resources/views/employee/reviews/show.blade.php`.

الملفات الجديدة:

- `app/Http/Controllers/DemoCivilRegistryController.php`
- `app/Http/Middleware/AuditDemoCivilRegistryDenials.php`
- `app/Http/Requests/DemoCivilPersonRequest.php` و`DemoCivilDocumentRequest.php`
- `app/Models/DemoCivilPerson.php` و`DemoCivilFamily.php` و`DemoCivilFamilyMember.php` و`DemoCivilRecord.php` و`DemoCivilRecordParty.php` و`DemoCivilDocument.php` و`DemoCivilRegistryAudit.php`
- `app/Policies/DemoCivilPersonPolicy.php` و`app/Services/DemoCivilRegistry/AuditLogger.php`
- `config/demo_civil_registry.php`
- `database/migrations/2026_11_11_000001_create_demo_civil_registry.php`
- `database/seeders/DemoCivilRegistrySeeder.php`
- `resources/views/components/demo-civil-registry-window.blade.php`
- `resources/views/admin/demo-civil-registry/index.blade.php` و`audits.blade.php`
- `public/css/demo-civil-registry.css`
- `public/js/demo-civil-registry.js` و`demo-civil-registry-admin.js` و`demo-civil-registry-fields.js`
- `tests/Feature/DemoCivilRegistryTest.php` و`tests/frontend/demo-civil-registry.test.cjs`
- هذا التقرير.

## الاختبارات والحدود

نتائج التشغيل الفعلية بتاريخ 11 نوفمبر 2026:

| الفحص | النتيجة |
| --- | --- |
| مجموعة السجل بعد آخر تعديل | **21 اختباراً ناجحاً، 795 assertion** |
| السجل والمراجعات وWorkspaceIsolation وWorkspaceExperience | **52 اختباراً ناجحاً، 1063 assertion**؛ قبل إضافة اختبار دورة الإدارة الأخير الذي نجح ضمن مجموعة السجل أعلاه |
| واجهة السجل | **6 اختبارات ناجحة** |
| كل اختبارات JavaScript الحالية | 54 اختباراً: 52 ناجحاً، إخفاق واحد، ومتجاوز واحد |
| مجموعة PHP الكاملة | 469 اختباراً: 463 ناجحاً، 5 إخفاقات، ومتجاوز واحد؛ 5223 assertion. شُغلت قبل آخر اختبارين للسجل، ثم أعيدت الاختبارات المركزة بعدهما |
| فحص صياغة PHP وJavaScript للملفات المعنية و`git diff --check` | ناجح |

كل تشغيل PHP استخدم SQLite `:memory:`. استُخدم PHPUnit مباشرة مع `--do-not-cache-result` لأن مغلف `artisan test` تعثر في اكتشاف مجلد العمل في البيئة الحالية؛ تجاوز حفظ نتيجة PHPUnit يمنع تعثر cache في البيئة نفسها. التشغيل الشامل فرض إعدادات SQLite وتجاهل config cache داخل عملية الاختبار فقط.

الإخفاقات الخمسة في PHP: اختباران في `CivilRegistryGuidesTest` لتوقعات محتوى صفحات الأدلة، واختبار `EmployeeReportingTest::test_empty_real_reports_and_csv_are_available_to_admin` لتوقع محتوى التقرير، واختباران في `UserAuthenticationTest` حيث ترفض قاعدة الاسم الرباعي بيانات التسجيل المستخدمة في الاختبار. لم تُعدّل ملفات تلك الميزات أو اختباراتها ضمن هذا العمل. لا تُعد النتيجة نجاحاً شاملاً للمشروع، وتبقى هذه الإخفاقات ظاهرة في `storage/logs/demo-registry-suite-final.txt` وملف JUnit المجاور.

إخفاق JavaScript هو `workspace-notifications.test.cjs` في اختبار إرسال النموذج؛ DOM الاختباري فيه لا يقدم `document.getElementById` الذي يستدعيه السكربت الحالي. لم يُغيّر سكربت مساحة العمل أو هذا الاختبار. التفاصيل في `storage/logs/demo-registry-frontend.txt`.

اختبارات السجل تشمل 100 شخص، إعادة التشغيل، الأنواع الثمانية، العلاقات والتواريخ، منح الوصول ومنعه، الفصل عن التفعيل والتحقق، قراءة الموظف فقط، البحث والصفحات والمدخلات غير الصالحة، الوثائق الخاصة والتلاعب بالمسار والمحتوى، الرفع والاستبدال والأرشفة، عرض الصفحات، سياق المراجعة وعدم تغيير بيانات الطلب، وحجب الإنتاج. أضيف أيضاً اختبار يبقي صفحة الصلاحيات القائمة مستقلة عن عمود الوحدة عند تعطيلها، حتى لا تتعطل قبل تطبيق migration في النسخة المخصصة. اختبارات JavaScript تشغّل تفاعل النافذة والبحث والأقسام والإدارة والنماذج باستخدام DOM اختباري.

لم تُجرَ معاينة بصرية بمتصفح فعلي أو اختبار لمس على هاتف، ولم تُختبر migrations على MySQL؛ ذلك متروك لنسخة اختبار معزولة. لا تُعد هذه المراجعة إثباتاً بصرياً لاختلافات المتصفحات. الوحدة لم تُفعّل على قاعدة المشروع؛ يلزم تجهيز نسخة الاختبار قبل الفحص اليدوي.

خطوات التحقق اليدوي: سجّل الدخول كأدمن في النسخة المعزولة، وابحث عن `DEMO-CIV-000001` ثم `DEMO-DOC-000301`، وافتح الزوجين وسجلاتهما. امنح موظفاً اختبارياً القراءة، وافتح طلب مراجعة بعد كتابة ملاحظة غير محفوظة، وافتح النافذة واسحبها وصغّرها وأغلقها بـEscape ثم أعد فتحها وتأكد من بقاء الملاحظة. جرّب أقساماً فارغة، وقطع الشبكة، والبحث السريع، وروابط أفراد الأسرة. اختبر رفع صورة ووثيقة واستبدالهما وأرشفتهما. اسحب الصلاحية وتحقق من رفض مسار الملف المباشر ومن حدث التدقيق.

المخاطر المتبقية: لا يفرض مخطط الوقائع العام كل قواعد الأحوال المدنية الممكنة؛ هذه محاكاة وليست سجلاً قانونياً. حدود 50 واقعة/وثيقة للقسم أو الملف مقصودة للبيانات الصغيرة الحالية، وتحتاج صفحات إضافية إذا توسعت العينة. الوثائق التجريبية مشتركة بصرياً ولا تمثل نسخ وثائق حكومية.
