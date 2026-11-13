<?php

namespace App\Http\Controllers;

use App\Services\FingerprintService;
use App\Services\Integrations\LocalCivilRegistryProvider;
use App\Services\Registration\MockCivilSystems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SimulationCenterController extends Controller
{
    public function index(Request $request)
    {
        $local = app()->environment(['local', 'testing']);
        $systems = [
            [
                'title' => 'السجل المدني التجريبي',
                'description' => 'البحث وعرض السجلات الاصطناعية. تبقى نافذة السجل متاحة للموظف المخوّل أثناء مراجعة الطلب.',
                'enabled' => $local && config('demo_civil_registry.enabled') === true,
                'tables' => ['demo_civil_people', 'demo_civil_families', 'demo_civil_family_members', 'demo_civil_records', 'demo_civil_record_parties', 'demo_civil_documents', 'demo_civil_registry_audits'],
                'route' => 'admin.demo-civil-registry.index',
            ],
            [
                'title' => 'أنظمة التسجيل والتفعيل المدنية',
                'description' => 'عرض أنظمة المحاكاة المدنية الحالية في نوافذ: السجل، المواليد، الأسرة، البطاقات، مطابقة الوجه ورموز التحقق.',
                'enabled' => MockCivilSystems::enabled(),
                'tables' => ['mock_civil_persons', 'mock_civil_families', 'mock_identity_cards', 'mock_birth_records', 'mock_civil_documents'],
                'route' => 'admin.mock-systems.index',
            ],
            [
                'title' => 'شبكة البريد التجريبية',
                'description' => 'إدارة المكاتب وحسابات المحصلين المستقلة. التحصيل غير متاح في هذه المرحلة؛ لم تُنفّذ الفواتير أو المدفوعات.',
                'enabled' => $local && (bool) config('postal_network.enabled'),
                'tables' => ['postal_offices', 'postal_collectors'],
                'route' => 'admin.postal-network.index',
            ],
            [
                'title' => 'محاكاة البصمة وإجراءات البطاقة',
                'description' => 'استقبال المواطنين وإجراءات البصمة والصورة التجريبية وفق الصلاحيات الحالية.',
                'enabled' => FingerprintService::canUse($request->user()),
                'tables' => ['identity_card_forms', 'identity_fingerprint_operations'],
                'route' => 'identity-workflow.reception',
                'secondary_route' => FingerprintService::canUse($request->user(), 'can_approve') ? 'identity-workflow.administration.index' : null,
            ],
            [
                'title' => 'تجربة التحقق من السجل المدني',
                'description' => 'اختبار محلي لنتائج المطابقة وعدم المطابقة وتعذر التحقق. لا يمثل تحققاً رسمياً ولا يستبدل البحث في السجلات.',
                'enabled' => LocalCivilRegistryProvider::enabled(),
                'tables' => [],
                'route' => 'integrations.civil-registry-demo',
            ],
        ];

        foreach ($systems as &$system) {
            $system['ready'] = $system['enabled'];
            if ($system['enabled']) {
                foreach ($system['tables'] as $table) {
                    if (! Schema::hasTable($table)) {
                        $system['ready'] = false;
                        break;
                    }
                }
            }
        }
        unset($system);

        return response()->view('admin.simulations', compact('systems'))->header('Cache-Control', 'no-store, private');
    }
}
