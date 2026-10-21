# المتطلب الخامس: التحقق من الأهلية والمتطلبات

تم تمديد `ServiceEligibility` الموجود، مع الاحتفاظ بدالة `reasons()` للمستدعين الحاليين. البيئة: Laravel 12.65.0، PHP 8.2.12، واتصال mysql بمحرك MariaDB 10.4.32 المحلي.

## السلوك

- نتيجة منظمة لكل شرط: مستوفى، غير مستوفى، يحتاج مراجعة، تعذر التحقق. تعرض الواجهة السبب والطرف المسؤول عن الإجراء.
- لا تسمح أي حالة غير مستوفاة بالكامل بتجاوز شرط إلزامي. غياب المصدر لا يعني النجاح، ورفع ملف لا يعادل اعتماده.
- فحص الحساب والبيانات الأساسية والتقييم الموثق الحالي والموانع والطلبات المكررة، والوثيقة السابقة وحالتها وتواريخها وفق القواعد المسجلة للخدمة.
- قواعد اختيارية للمرفقات والصورة الشخصية والفحص الطبي للقيادة وأصالة الوثيقة ومصدر الموانع. لا توجد شروط قانونية أو نتائج طبية مفترضة.
- إعادة الفحص عند المعاينة والتقديم النهائي، وحفظ النتيجة والتوقيت مع الطلب. زر إعادة الفحص للمخولين لا يغير حالة الطلب أو بياناته التاريخية.
- حجز سجلات المواطن ثم الخدمة ثم الطلب داخل transaction عند إعادة الفحص وعند الانتقالات المستقبلية المحمية. يعاد التحقق قبل القبول/الاعتماد/المعالجة دون منح أي دور صلاحيات المراجعة المستقبلية.
- فصل سبب المنع الداخلي عن `public_block_reason`؛ السجلات تحتوي معرفات داخلية ورموز الفحوص وحالاتها دون أسباب سرية أو ملفات أو أرقام وثائق أو بيانات اتصال.

## أهم الملفات

- `app/Services/ServiceEligibility.php`، `app/Services/ServiceRequestSubmission.php`.
- `app/Services/Requirements/`: نتائج الفحوص، الوثائق السابقة، الأدلة، والمصدر غير المتاح الافتراضي.
- `app/Contracts/ServiceEvidenceSource.php` و`app/Enums/RequirementState.php`.
- `app/Models/GovernmentService.php`، `CitizenServiceAssessment.php`، `CitizenServiceRequest.php`.
- `app/Http/Controllers/CitizenDataController.php` و`GovernmentServiceController.php`.
- `app/Http/Requests/SaveGovernmentServiceRequest.php` و`SaveServiceAssessmentRequest.php`.
- `app/Providers/AppServiceProvider.php` و`routes/web.php`.
- `resources/views/components/requirements-result.blade.php` و`request-details.blade.php`، وصفحات بدء الخدمة وتفاصيلها وإعدادات المدير.
- `database/migrations/2026_10_25_000001_extend_service_requirements_validation.php`: حقول القواعد والسبب العام ونتيجة الفحص وتوقيته فقط.
- `database/migrations/2026_10_25_000002_preserve_explicit_assessment_review_time.php`: إزالة التحديث التلقائي الضمني لوقت المراجعة في MariaDB مع الحفاظ على القيم الموجودة. اكتشف اختبار MySQL أن هذا السلوك كان يجعل التقييم يبدو مستقبليًا بعد تعديل حقول أخرى.
- `tests/Feature/ServiceRequirementsTest.php` وتحديث اختبار خصوصية سبب المنع في `GovernmentServicesTest.php`.

## التكامل المطلوب لاحقًا

العقد `ServiceEvidenceSource::lookup()` يستقبل المواطن والخدمة ونوع الدليل ومفتاحه والطلب الاختياري، ويعيد `EvidenceLookup(available, records)`. المصدر الافتراضي **غير متاح**؛ لا يوجد تكامل حكومي أو رفع ملفات أو فحص طبي فعلي مصطنع.

على المحول الموثوق إعادة معرفات `user_id`, `service_id`, و`request_id` عندما يرتبط الدليل بطلب. يقبل `request_id=null` للأدلة المرتبطة بالمواطن والخدمة قبل إنشاء الطلب؛ يجب على التكامل تحديد صلاحية إعادة استخدامها. يلزم `trusted=true`, `source_reference`, و`verified_at` صحيح غير مستقبلي، و`valid_until` صالح إن أرسل. لا تؤخذ هذه القيم من المتصفح.

- المرفقات: `attachment:<rule code>` مع `uploaded`, `mime_type`, `review_status`، و`expires_at` إن اشترط أو توفر. يجب أن يثبت المصدر نوع الملف فعليًا؛ ليس امتداد الاسم أو تصريح المتصفح.
- الفحص الطبي: `medical:driving` مع `result=passed|failed|pending`, `examined_at`, و`expires_at` حسب القاعدة.
- الأصالة: `document:<document id>` مع `document_id` و`result=verified|invalid|pending`.
- الموانع: `restrictions:clearance` مع `result=clear|blocked|pending`. لا ينشر المحرك تفاصيل المصدر الداخلية.

تُضبط القواعد من إعدادات الخدمة في `verification_rules`، مع ربط كل قاعدة مستند باسم ضمن `required_documents`. تستخدم أعلام JSON قيم `true` و`false`. تغيير القواعد يغير إصدارها ويبطل التقييمات القديمة. يحتاج التشغيل الحقيقي مصادر موثوقة وقواعد معتمدة من الجهة المختصة، ثم وحدات الرفع والمراجعة اللاحقة.

## التحقق والتشغيل

```powershell
php artisan migrate --force
php vendor/bin/phpunit --do-not-cache-result
php vendor/bin/phpunit tests/Feature/ServiceRequirementsTest.php --do-not-cache-result
node --test tests/frontend/portal-demo.test.cjs
php artisan view:cache
```

لا تستخدم `migrate:fresh`. اختبارات البيانات تشترط SQLite بالذاكرة أو قاعدة اسمها ينتهي بـ `_testing`، وتعيد بيانات الاختبار عبر معاملات. لا تشغلها على قاعدة التشغيل.

لاختبارات MySQL، عيّن متغيرات اتصال قاعدة اختبار مستقلة، ثم شغل المجموعة نفسها و:

```powershell
php vendor/bin/phpunit tests/Integration/ServiceRequestConcurrencyTest.php --do-not-cache-result
```

التحقق الفعلي: المجموعة الكاملة قبل آخر اختبار انحدار نجحت على SQLite وMySQL (80 اختبارًا، 595 تأكيدًا لكل منهما). مجموعة المتطلب النهائية على MySQL نجحت (17 اختبارًا، 71 تأكيدًا)، واختبارا التزامن نجحا (11 تأكيدًا)، واختبارات واجهة العرض التجريبية الخمسة نجحت. لا تمثل اختبارات JavaScript التجريبية تكاملًا حكوميًا حقيقيًا. أضيفت النتيجة النهائية للمجموعة الكاملة أدناه بعد تشغيلها.

لم يتم اختبار الواجهات بصريًا عبر المتصفح؛ تم تجميع قوالب Blade واختبار مسارات HTTP والصلاحيات. لا تشمل هذه المهمة رفع المستندات أو المدفوعات أو إدارة الفحوص أو الاعتماد والإصدار.

التحقق النهائي الكامل على SQLite: 81 اختبارًا و597 تأكيدًا ناجحًا. فحص Pint ناجح. طُبّقت migration الإضافية وإصلاح تاريخ المراجعة في قاعدة التشغيل؛ بقيت أعداد السجلات: مستخدم واحد، 7 خدمات، وصفر طلبات. أكدت مراجعة مستقلة إصلاح تعارض إعادة الفحص ورفض تواريخ الصلاحية غير الصحيحة.

التحقق النهائي الكامل على MySQL نجح أيضًا: 81 اختبارًا و597 تأكيدًا.
