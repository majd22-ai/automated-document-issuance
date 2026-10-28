# إصدار البطاقة الشخصية — الجزء الأول

## البنية والتنفيذ

المشروع Laravel 12.65.0 وPHP 8.2.12، بواجهات Blade عربية وأدوار المواطن والموظف والمدير. أُعيد استخدام `government_services` وخدمة `identity-first-issue` وجدول الطلبات `citizen_service_requests` ومسار الإرسال والأهلية والإشعارات الحالي. الصور تستخدم `citizen_attachments` وقرص `attachments` الخاص ومراجعة الموظف الموجودة.

أضيف جدول `identity_card_forms` لمسودة النموذج والبيانات المقدمة وارتباطها بطلب واحد وصورته، وجدول `identity_card_witnesses` لشاهدين. بيانات النموذج لقطة خاصة بالطلب ولا تعدّل البيانات الرسمية في حساب المواطن. لا أرقام بطاقات حكومية مُولّدة؛ مرجع المسودة `IDFORM-…` ومرجع الطلب `REQ-…` داخليان.

المراحل: البيانات الشخصية، الميلاد والجنسية، العنوان والحالة الاجتماعية والتعليم والمهنة، الصورة، الشاهدان، الملخص والإقرار، تأكيد التقديم. يمكن حفظ البيانات الناقصة كمسودة، لكن المتابعة تتطلب حقول المرحلة، والإرسال يعيد التحقق من الجميع. حقل الديانة اختياري. التاريخ الهجري يدوي دون ادعاء التحويل الآلي أو التحقق الرسمي.

الإرسال يحافظ على شروط تفعيل الخدمة والأهلية المعتمدة الحالية؛ لا يمنح حسابًا هوية موثقة ولا يعتمد خدمة غير مفعّلة. إذا كانت قواعد الخدمة تتطلب مراجعة مرفق قبل التقديم، تبقى تلك القاعدة سارية. لا تُغيّر رسوم أو اعتمادات أو سجلات حقيقية تلقائيًا.

الصورة JPG/JPEG/PNG، بحد افتراضي 2 ميجابايت و600×600 بكسل؛ القيم في `config/identity_card.php`. تُراجع على الخادم وتخزن باسم UUID في مساحة خاصة، وتعرض عبر مسار مصرح به. الاستبدال يعيد حالة المراجعة إلى الانتظار؛ لا يثبت النظام آليًا هوية صاحب الصورة. يستمر تطبيق القيود عند الاستبدال من مسار المرفقات العام. عند طلب الموظف استكمالًا، يُستخدم مسار المرفقات الحالي.

الحالات المحجوزة للتوسع موضحة في `IdentityCardForm::STATUSES`. المطبق الآن المسودة واستكمال البيانات ثم انتظار مراجعة الصورة عند إرسال الطلب. التحقق من الشاهد `not_verified` وحضوره `not_recorded`، دون حضور أو موافقة مفترضة. لا مصادقات خارجية أو محاكاة بصمة جديدة في هذا الجزء.

## الملفات

الجديدة:

- `config/identity_card.php`
- `database/migrations/2026_10_29_000001_create_identity_card_forms.php`
- `app/Models/IdentityCardForm.php` و`IdentityCardWitness.php`
- `app/Services/IdentityCardForms.php`
- `app/Http/Requests/SaveIdentityCardForm.php`
- `app/Http/Controllers/IdentityCardFormController.php`
- `resources/views/citizen/identity-card/index.blade.php` و`wizard.blade.php`
- `resources/views/employee/identity-card/show.blade.php`
- `resources/views/components/identity-card-summary.blade.php`
- `public/css/identity-card-wizard.css` و`public/js/identity-card-wizard.js`
- `tests/Feature/IdentityCardWizardTest.php`
- `tests/frontend/identity-card-wizard.test.cjs`

المعدلة للربط وإعادة الاستخدام:

- `routes/web.php`
- `app/Models/User.php` و`CitizenServiceRequest.php`
- `app/Http/Requests/PreviewServiceRequest.php`
- `app/Services/ServiceEligibility.php`
- `app/Services/Attachments/AttachmentRules.php` و`ManageAttachments.php`
- `resources/views/citizen/dashboard.blade.php`
- `resources/views/public/services/show.blade.php`
- `resources/views/employee/reviews/show.blade.php`

## التشغيل والتحقق

طُبقت الـMigration محليًا بنجاح دون حذف بيانات. على بيئة أخرى:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

رابط المواطن: `/citizen/identity-card`. يصل الموظف إلى الاستمارة من صفحة مراجعة الطلب عند امتلاكه صلاحية مراجعته.

الاختبارات الفعلية:

- المجموعة الكاملة: 331 اختبارًا و2514 تحققًا نجحت.
- بعد حماية مسار استبدال المرفقات، فحص الخدمة والمرفقات على MySQL المعزول: 23 اختبارًا و267 تحققًا نجحت.
- 14 اختبار JavaScript نجحت، بينها معاينة الصورة وتنظيف روابطها المؤقتة.
- تجميع Blade وفحص Pint نجحا. أُجريت مراجعة كود مستقلة وأُصلحت ملاحظتا حذف الملفات القديمة وتغيير الصورة بعد الملخص.
- لم تتوفر معاينة بصرية في متصفح متصل؛ اختبارات HTTP في Laravel تغطي عرض المراحل والصلاحيات.

```powershell
php vendor/bin/phpunit tests/Feature/IdentityCardWizardTest.php tests/Feature/CitizenAttachmentsTest.php --do-not-cache-result
node --test tests/frontend/identity-card-wizard.test.cjs
php artisan view:cache
php artisan view:clear
```

تُشغّل اختبارات قاعدة البيانات على SQLite الذاكرة أو قاعدة اختبار معزولة ينتهي اسمها بـ `_testing` فقط.
