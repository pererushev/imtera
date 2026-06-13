<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function reviews(Request $request): JsonResponse
    {
        $organization = Organization::where('user_id', $request->user()->id)->first();

        if (! $organization) {
            return response()->json(['message' => 'Организация не настроена'], 404);
        }

        if ($organization->parse_status !== 'success') {
            return response()->json([
                'message' => 'Данные организации ещё не загружены',
                'parse_status' => $organization->parse_status,
                'parse_error' => $organization->parse_error,
            ], 422);
        }

        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));

        $reviews = $organization->reviews()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'organization' => [
                'name' => $organization->name,
                'rating' => $organization->rating ? (float) $organization->rating : null,
                'reviews_count' => $organization->reviews_count,
                'ratings_count' => $organization->ratings_count,
                'fetched_reviews_count' => $reviews->total(),
            ],
            'reviews' => collect($reviews->items())->map(fn ($review) => [
                'id' => $review->id,
                'author' => $review->author,
                'date' => $review->reviewed_at?->toIso8601String(),
                'text' => $review->text,
                'rating' => $review->rating,
            ]),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
