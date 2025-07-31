<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Get paginated customers with optional filtering
     */
    public function getCustomers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::with(['group', 'ledgerEntries' => function ($q) {
            $q->latest()->limit(5);
        }]);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['group_id'])) {
            $query->inGroup($filters['group_id']);
        }

        if (!empty($filters['customer_type'])) {
            $query->where('customer_type', $filters['customer_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['has_credit_limit'])) {
            $query->where('credit_limit', '>', 0);
        }

        if (!empty($filters['exceeded_credit'])) {
            $query->whereRaw('current_balance > credit_limit');
        }

        // Sort options
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        DB::beginTransaction();
        
        try {
            $customer = Customer::create($data);
            
            // Create initial ledger entry if there's an opening balance
            if (!empty($data['opening_balance']) && $data['opening_balance'] != 0) {
                $this->createLedgerEntry($customer, [
                    'transaction_type' => $data['opening_balance'] > 0 ? 'credit' : 'debit',
                    'amount' => abs($data['opening_balance']),
                    'description' => 'Opening balance',
                    'reference_type' => 'opening_balance',
                    'transaction_date' => now(),
                ]);
            }

            DB::commit();
            return $customer->load('group');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update customer information
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->load('group');
    }

    /**
     * Delete customer (soft delete by marking inactive)
     */
    public function deleteCustomer(Customer $customer): bool
    {
        // Check if customer has any invoices
        if ($customer->invoices()->exists()) {
            // Don't actually delete, just mark as inactive
            $customer->update(['is_active' => false]);
            return true;
        }

        // If no invoices, can safely delete
        return $customer->delete();
    }

    /**
     * Get customer with detailed information
     */
    public function getCustomerDetails(int $customerId): Customer
    {
        return Customer::with([
            'group',
            'invoices' => function ($q) {
                $q->latest()->limit(10);
            },
            'ledgerEntries' => function ($q) {
                $q->latest()->limit(20);
            }
        ])->findOrFail($customerId);
    }

    /**
     * Create ledger entry for customer
     */
    public function createLedgerEntry(Customer $customer, array $data): CustomerLedger
    {
        // Calculate new balance
        $currentBalance = $customer->current_balance;
        $goldBalance = $customer->gold_balance ?? 0;

        if ($data['transaction_type'] === 'credit') {
            $newBalance = $currentBalance + $data['amount'];
            $newGoldBalance = $goldBalance + ($data['gold_amount'] ?? 0);
        } else {
            $newBalance = $currentBalance - $data['amount'];
            $newGoldBalance = $goldBalance - ($data['gold_amount'] ?? 0);
        }

        // Create ledger entry
        $ledgerEntry = CustomerLedger::create([
            'customer_id' => $customer->id,
            'transaction_type' => $data['transaction_type'],
            'amount' => $data['amount'],
            'gold_amount' => $data['gold_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'IRR',
            'description' => $data['description'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'balance_after' => $newBalance,
            'gold_balance_after' => $newGoldBalance,
            'transaction_date' => $data['transaction_date'] ?? now(),
            'created_by' => auth()->id(),
        ]);

        // Update customer balance
        $customer->update([
            'current_balance' => $newBalance,
            'gold_balance' => $newGoldBalance,
            'last_transaction_at' => now(),
        ]);

        return $ledgerEntry;
    }

    /**
     * Get customer ledger with pagination
     */
    public function getCustomerLedger(int $customerId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CustomerLedger::with('creator')
            ->forCustomer($customerId);

        // Apply filters
        if (!empty($filters['transaction_type'])) {
            $query->byType($filters['transaction_type']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }

        if (!empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        return $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get customers with birthdays in the next N days
     */
    public function getUpcomingBirthdays(int $days = 7): Collection
    {
        $customers = Customer::active()
            ->whereNotNull('birth_date')
            ->get();

        return $customers->filter(function ($customer) use ($days) {
            return $customer->isBirthdayWithinDays($days);
        });
    }

    /**
     * Get customers with birthdays today
     */
    public function getTodaysBirthdays(): Collection
    {
        $customers = Customer::active()
            ->whereNotNull('birth_date')
            ->get();

        return $customers->filter(function ($customer) {
            return $customer->isBirthdayToday();
        });
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(): array
    {
        $totalCustomers = Customer::active()->count();
        $newThisMonth = Customer::active()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $topCustomers = Customer::active()
            ->withSum('invoices as total_purchases', 'total_amount')
            ->orderBy('total_purchases', 'desc')
            ->limit(10)
            ->get();

        $creditLimitExceeded = Customer::active()
            ->whereRaw('current_balance > credit_limit')
            ->where('credit_limit', '>', 0)
            ->count();

        $upcomingBirthdays = $this->getUpcomingBirthdays()->count();

        return [
            'total_customers' => $totalCustomers,
            'new_this_month' => $newThisMonth,
            'credit_limit_exceeded' => $creditLimitExceeded,
            'upcoming_birthdays' => $upcomingBirthdays,
            'top_customers' => $topCustomers,
        ];
    }

    /**
     * Import customers from CSV data
     */
    public function importCustomers(array $customersData): array
    {
        $imported = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($customersData as $index => $customerData) {
                try {
                    // Validate required fields
                    if (empty($customerData['name'])) {
                        $errors[] = "Row " . ($index + 1) . ": Name is required";
                        continue;
                    }

                    // Check if customer already exists
                    $existingCustomer = Customer::where('email', $customerData['email'])
                        ->orWhere('phone', $customerData['phone'])
                        ->first();

                    if ($existingCustomer) {
                        $errors[] = "Row " . ($index + 1) . ": Customer already exists";
                        continue;
                    }

                    // Create customer
                    $this->createCustomer($customerData);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            return [
                'imported' => $imported,
                'errors' => $errors,
                'total' => count($customersData),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Export customers to array format
     */
    public function exportCustomers(array $filters = []): array
    {
        $query = Customer::with('group');

        // Apply same filters as getCustomers
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['group_id'])) {
            $query->inGroup($filters['group_id']);
        }

        if (!empty($filters['customer_type'])) {
            $query->where('customer_type', $filters['customer_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $customers = $query->get();

        return $customers->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'city' => $customer->city,
                'postal_code' => $customer->postal_code,
                'tax_id' => $customer->tax_id,
                'national_id' => $customer->national_id,
                'customer_type' => $customer->customer_type,
                'group_name' => $customer->group?->name,
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'birth_date' => $customer->birth_date?->format('Y-m-d'),
                'is_active' => $customer->is_active ? 'Yes' : 'No',
                'created_at' => $customer->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}