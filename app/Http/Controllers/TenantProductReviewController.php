<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantProductReviewRequest;
use App\Models\User;
use App\Services\Reviews\TenantProductReviewService;
use App\Support\Database\PublicSchema;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantProductReviewController extends Controller
{
    public function store(
        StoreTenantProductReviewRequest $request,
        TenantManager $tenancy,
        TenantProductReviewService $reviews,
    ): RedirectResponse {
        $context = $this->assertReady($request, $tenancy);

        /** @var User $user */
        $user = $request->user();

        $reviews->submit($user, $context->tenant, [
            'rating' => (int) $request->validated('rating'),
            'comment' => (string) $request->validated('comment'),
        ]);

        return back()->with('success', 'Gracias. Tu reseña se publicará en la ficha de VetSaaS en Orvae.');
    }

    public function dismiss(
        Request $request,
        TenantManager $tenancy,
        TenantProductReviewService $reviews,
    ): RedirectResponse {
        $context = $this->assertReady($request, $tenancy);

        /** @var User $user */
        $user = $request->user();
        $reviews->dismiss($user, $context->tenant);

        return back();
    }

    private function assertReady(Request $request, TenantManager $tenancy): \App\Tenancy\TenantContext
    {
        abort_unless(PublicSchema::hasTable('tenant_product_reviews'), 404);
        abort_unless($request->user() instanceof User, 403);

        $context = $tenancy->current();
        abort_unless($context !== null, 404);
        abort_if($context->slug === 'demo', 404);

        $impersonating = is_array($request->session()->get('tenant_impersonation'))
            && ! empty($request->session()->get('tenant_impersonation')['tenant_id']);
        abort_if($impersonating, 403);

        return $context;
    }
}
