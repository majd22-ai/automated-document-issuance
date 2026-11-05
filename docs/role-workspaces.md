# لوحات العمل والصلاحيات

تم تنظيم لوحة الموظف حول مهامه الفعلية والخدمات التي فوّضه المدير بها، وتنظيم لوحة المواطن حول طلباته وحالته. أصبحت إدارة المستخدمين تتضمن البحث وتصفية الدور والحالة، مع فتح تعديل الحساب عند الحاجة، دون تكرار روابط الأقسام داخل لوحة العمل.

## مصادر الوصول

- `EmployeeWorkspace` يجمع وحدات التنقل من الصلاحيات الموجودة فقط؛ لا يمنح صلاحيات.
- مراجعة الطلبات: can_review أو can_approve أو can_assign؛ وتظل إجراءات كل طلب محكومة بسياساتها.
- المرفقات والمواعيد والفترات والعمليات المالية: can_review ضمن الخدمة المحددة.
- التحقق: can_verify؛ الإصدار: can_issue؛ التسليم: can_deliver.
- تفعيل الحسابات: صلاحية التفعيل الموجودة؛ التقارير: نطاق القسم الموجود.
- عاقل الحارة: شهادات السكن الموجهة إليه وفق صلاحية العاقل القائمة. لا يرى وحدات الموظفين الأخرى إلا عند منح صلاحيات إضافية صريحة من المدير.
- موظف بلا صلاحيات خدمة لا يرى الوحدات التشغيلية، ولا يستطيع دخولها بالرابط المباشر.
- البحث عن المواطنين وملفاتهم والوثائق والطلبات والمرفقات والحجوزات والمدفوعات مقيدة بالخدمات المفوضة، وليس بدور الموظف وحده.

لم تُنشأ أدوار أو جداول أو منح صلاحيات تلقائية. لم تُغيّر بيانات قاعدة التشغيل. التغييرات السابقة الخاصة بالتفعيل الإلكتروني محفوظة.

## الملفات الرئيسية

- app/Services/Workspace/EmployeeWorkspace.php
- app/Http/Controllers/WorkspaceController.php
- app/Http/Middleware/EnsureUserHasRole.php
- سياسات User وCitizenServiceRequest وCitizenAttachment وAppointmentSlot.
- Controllers: AdminUser، CitizenData، CitizenAttachment، Appointment، RequestOperations، CivilRegistrySimulation.
- routes/web.php
- واجهات employee/dashboard وcitizen/dashboard وadmin/users/index وadmin/reports/index.
- components/account-navigation وcomponents/operations-sidebar وlayouts/public.
- public/css/operations-workspace.css وpublic/js/operations-workspace.js.
- tests/Feature/WorkspaceIsolationTest.php وtests/Support/GrantsWorkspacePermissions.php وtests/JavaScript/operations-workspace.test.mjs؛ تحديث تجهيزات اختبارات الموظفين لمنح صلاحيات اختبار صريحة.

## التحقق الفعلي

- اختبارات Laravel الكاملة: 410 ناجحة، اختبار واحد متخطى، وفشلان سابقان مستقلان في CivilRegistryGuidesTest وGovernmentServicesTest متعلقان بروابط الصفحة الرئيسية. لم تُحذف الاختبارات أو تُتجاهل.
- الاختبارات المركزة للمراجعة والإصدار والتتبع والعاقل والتفعيل واللوحات: 167 ناجحة (1358 تحققاً).
- بعد تنسيق PHP: WorkspaceIsolationTest، ستة اختبارات ناجحة (55 تحققاً).
- JavaScript: ثلاثة اختبارات ناجحة، تشمل طي قائمة مساحة العمل على الجوال وإظهارها على الكمبيوتر.
- npm.cmd run build: ناجح. بقي تنبيه Vite حول header-left.webp يُحل مساره وقت التشغيل.
- php artisan view:cache: ناجح.
- لم يُجر اختبار بصري على هاتف فعلي أو متصفح تفاعلي؛ استجابة الواجهة مدعومة بـ CSS واختبار سلوك القائمة، وليست ادعاء تحقق بصري شامل.

أوامر إعادة التحقق: `php artisan test`، `node --test tests/JavaScript/*.test.mjs`، `npm.cmd run build`، `php artisan view:cache`. لا توجد ترحيلات جديدة لهذه المهمة.
