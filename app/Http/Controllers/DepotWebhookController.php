<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * GitLab calls this when someone pushes to mqmesh-depot, so new meshes show
 * up within a minute instead of waiting for the next scheduled sync.
 *
 * Set it up in GitLab under Settings, Webhooks: URL /hooks/gitlab, the secret
 * token from MQMESH_WEBHOOK_SECRET, trigger "Push events".
 */
class DepotWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('mqmesh.depot.webhook_secret');

        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Gitlab-Token')), 403);

        if ($request->header('X-Gitlab-Event') !== 'Push Hook') {
            return response()->json(['status' => 'ignored']);
        }

        // Answer GitLab straight away, then sync.
        dispatch(fn () => Artisan::call('mqmesh:sync-depot'))->afterResponse();

        return response()->json(['status' => 'syncing'], 202);
    }
}
