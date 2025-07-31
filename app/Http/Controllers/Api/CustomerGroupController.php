<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerGroupController extends Controller
{
    /**
     * Get all customer groups
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerGroup::query();

        // Filter by active status
        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        // Include customer count
        if ($request->has('with_customer_count') && $request->boolean('with_customer_count')) {
            $query->withCount('customers');
        }

        $groups = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Create a new customer group
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:customer_groups,name',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'credit_limit_multiplier' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        try {
            $group = CustomerGroup::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer group created successfully',
                'data' => $group,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer group details
     */
    public function show(CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->load(['customers' => function ($query) {
            $query->active()->limit(10);
        }]);

        $customerGroup->loadCount('customers');

        return response()->json([
            'success' => true,
            'data' => $customerGroup,
        ]);
    }

    /**
     * Update customer group
     */
    public function update(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:customer_groups,name,' . $customerGroup->id,
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'credit_limit_multiplier' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        try {
            $customerGroup->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer group updated successfully',
                'data' => $customerGroup,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete customer group
     */
    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        try {
            // Check if group has customers
            if ($customerGroup->customers()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group with existing customers',
                ], 422);
            }

            $customerGroup->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer group deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customers in a specific group
     */
    public function customers(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        
        $customers = $customerGroup->customers()
            ->with('ledgerEntries')
            ->when($request->has('search'), function ($query) use ($request) {
                $query->search($request->get('search'));
            })
            ->when($request->has('active_only') && $request->boolean('active_only'), function ($query) {
                $query->active();
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Move customers to another group
     */
    public function moveCustomers(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
            'target_group_id' => 'nullable|exists:customer_groups,id',
        ]);

        try {
            $customerGroup->customers()
                ->whereIn('id', $validated['customer_ids'])
                ->update(['customer_group_id' => $validated['target_group_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Customers moved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to move customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}