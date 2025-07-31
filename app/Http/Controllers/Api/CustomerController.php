<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Get paginated list of customers
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'group_id', 'customer_type', 'is_active',
            'has_credit_limit', 'exceeded_credit', 'sort_by', 'sort_direction'
        ]);

        $perPage = $request->get('per_page', 15);
        $customers = $this->customerService->getCustomers($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Create a new customer
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50|unique:customers,tax_id',
            'national_id' => 'nullable|string|max:20|unique:customers,national_id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'customer_type' => ['required', Rule::in(['individual', 'business'])],
            'credit_limit' => 'nullable|numeric|min:0',
            'birth_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'contact_preferences' => 'nullable|array',
            'contact_preferences.*' => 'string|in:email,sms,phone,whatsapp',
            'opening_balance' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        try {
            $customer = $this->customerService->createCustomer($validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer details
     */
    public function show(int $customerId): JsonResponse
    {
        try {
            $customer = $this->customerService->getCustomerDetails($customerId);

            return response()->json([
                'success' => true,
                'data' => $customer,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }
    }

    /**
     * Update customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:customers,phone,' . $customer->id,
            'email' => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50|unique:customers,tax_id,' . $customer->id,
            'national_id' => 'nullable|string|max:20|unique:customers,national_id,' . $customer->id,
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'customer_type' => ['required', Rule::in(['individual', 'business'])],
            'credit_limit' => 'nullable|numeric|min:0',
            'birth_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'contact_preferences' => 'nullable|array',
            'contact_preferences.*' => 'string|in:email,sms,phone,whatsapp',
            'is_active' => 'boolean',
        ]);

        try {
            $customer = $this->customerService->updateCustomer($customer, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $deleted = $this->customerService->deleteCustomer($customer);

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Customer deleted successfully' : 'Customer deactivated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer ledger
     */
    public function ledger(Request $request, int $customerId): JsonResponse
    {
        $filters = $request->only(['transaction_type', 'start_date', 'end_date', 'reference_type']);
        $perPage = $request->get('per_page', 20);

        try {
            $ledger = $this->customerService->getCustomerLedger($customerId, $filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $ledger,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get customer ledger',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create ledger entry for customer
     */
    public function createLedgerEntry(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'transaction_type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0',
            'gold_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:IRR,USD,EUR',
            'description' => 'required|string|max:500',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'transaction_date' => 'nullable|date',
        ]);

        try {
            $ledgerEntry = $this->customerService->createLedgerEntry($customer, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Ledger entry created successfully',
                'data' => $ledgerEntry,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ledger entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->customerService->getCustomerStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get upcoming birthdays
     */
    public function upcomingBirthdays(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);

        try {
            $customers = $this->customerService->getUpcomingBirthdays($days);

            return response()->json([
                'success' => true,
                'data' => $customers,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upcoming birthdays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get today's birthdays
     */
    public function todaysBirthdays(): JsonResponse
    {
        try {
            $customers = $this->customerService->getTodaysBirthdays();

            return response()->json([
                'success' => true,
                'data' => $customers,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get today\'s birthdays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import customers from CSV
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customers' => 'required|array',
            'customers.*.name' => 'required|string|max:255',
            'customers.*.phone' => 'nullable|string|max:20',
            'customers.*.email' => 'nullable|email|max:255',
            'customers.*.address' => 'nullable|string',
            'customers.*.city' => 'nullable|string|max:100',
            'customers.*.postal_code' => 'nullable|string|max:20',
            'customers.*.tax_id' => 'nullable|string|max:50',
            'customers.*.national_id' => 'nullable|string|max:20',
            'customers.*.customer_type' => 'nullable|in:individual,business',
            'customers.*.credit_limit' => 'nullable|numeric|min:0',
            'customers.*.birth_date' => 'nullable|date',
            'customers.*.opening_balance' => 'nullable|numeric',
        ]);

        try {
            $result = $this->customerService->importCustomers($validated['customers']);

            return response()->json([
                'success' => true,
                'message' => 'Import completed',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export customers
     */
    public function export(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'group_id', 'customer_type', 'is_active'
        ]);

        try {
            $customers = $this->customerService->exportCustomers($filters);

            return response()->json([
                'success' => true,
                'data' => $customers,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}