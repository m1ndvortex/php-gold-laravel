<template>
  <div class="accounting-module" dir="rtl">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">حسابداری و مالی</h1>
          <p class="text-sm text-gray-600 mt-1">مدیریت حساب‌ها، اسناد حسابداری و گزارش‌های مالی</p>
        </div>
        <div class="flex space-x-3 space-x-reverse">
          <button
            @click="activeTab = 'chart-of-accounts'"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              activeTab === 'chart-of-accounts'
                ? 'bg-blue-100 text-blue-700'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
            ]"
          >
            دفتر حساب‌ها
          </button>
          <button
            @click="activeTab = 'journal-entries'"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              activeTab === 'journal-entries'
                ? 'bg-blue-100 text-blue-700'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
            ]"
          >
            اسناد حسابداری
          </button>
          <button
            @click="activeTab = 'reports'"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              activeTab === 'reports'
                ? 'bg-blue-100 text-blue-700'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
            ]"
          >
            گزارش‌های مالی
          </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="p-6">
      <!-- Chart of Accounts Tab -->
      <div v-if="activeTab === 'chart-of-accounts'" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">دفتر حساب‌ها</h2>
          <div class="flex space-x-3 space-x-reverse">
            <button
              @click="initializeChart"
              :disabled="accounts.length > 0 || loading"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              ایجاد دفتر استاندارد
            </button>
            <button
              @click="showCreateAccountModal = true"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              حساب جدید
            </button>
          </div>
        </div>

        <!-- Accounts Filter -->
        <div class="bg-white rounded-lg shadow p-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">نوع حساب</label>
              <select
                v-model="accountsFilter.type"
                @change="loadAccounts"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="">همه انواع</option>
                <option value="asset">دارایی</option>
                <option value="liability">بدهی</option>
                <option value="equity">حقوق صاحبان سهام</option>
                <option value="revenue">درآمد</option>
                <option value="expense">هزینه</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">وضعیت</label>
              <select
                v-model="accountsFilter.active_only"
                @change="loadAccounts"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option :value="false">همه حساب‌ها</option>
                <option :value="true">فقط فعال</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Accounts Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    کد حساب
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    نام حساب
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    نوع
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    موجودی جاری
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    وضعیت
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    عملیات
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="account in accounts" :key="account.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ account.code }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ account.name }}</div>
                    <div class="text-sm text-gray-500">{{ account.full_path }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="getAccountTypeClass(account.type)" class="px-2 py-1 text-xs font-medium rounded-full">
                      {{ getAccountTypeLabel(account.type) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatCurrency(account.current_balance) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="account.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                          class="px-2 py-1 text-xs font-medium rounded-full">
                      {{ account.is_active ? 'فعال' : 'غیرفعال' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      v-if="!account.is_system"
                      @click="editAccount(account)"
                      class="text-blue-600 hover:text-blue-900 ml-3"
                    >
                      ویرایش
                    </button>
                    <button
                      @click="viewGeneralLedger(account)"
                      class="text-green-600 hover:text-green-900"
                    >
                      دفتر کل
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Journal Entries Tab -->
      <div v-if="activeTab === 'journal-entries'" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">اسناد حسابداری</h2>
          <button
            @click="showCreateJournalModal = true"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            سند جدید
          </button>
        </div>

        <!-- Journal Entries Filter -->
        <div class="bg-white rounded-lg shadow p-4">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">وضعیت</label>
              <select
                v-model="journalFilter.status"
                @change="loadJournalEntries"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="">همه وضعیت‌ها</option>
                <option value="draft">پیش‌نویس</option>
                <option value="posted">ثبت شده</option>
                <option value="reversed">برگشت خورده</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">از تاریخ</label>
              <input
                v-model="journalFilter.start_date"
                @change="loadJournalEntries"
                type="date"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">تا تاریخ</label>
              <input
                v-model="journalFilter.end_date"
                @change="loadJournalEntries"
                type="date"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
            </div>
          </div>
        </div>

        <!-- Journal Entries Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    شماره سند
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    تاریخ
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    شرح
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    مبلغ
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    وضعیت
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    عملیات
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="entry in journalEntries.data" :key="entry.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ entry.entry_number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(entry.entry_date) }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-900">
                    {{ entry.description }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatCurrency(entry.total_debit) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="getJournalStatusClass(entry.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                      {{ getJournalStatusLabel(entry.status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      v-if="entry.status === 'draft'"
                      @click="postJournalEntry(entry)"
                      class="text-green-600 hover:text-green-900 ml-3"
                    >
                      ثبت
                    </button>
                    <button
                      v-if="entry.status === 'posted'"
                      @click="reverseJournalEntry(entry)"
                      class="text-red-600 hover:text-red-900 ml-3"
                    >
                      برگشت
                    </button>
                    <button
                      @click="viewJournalEntry(entry)"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      مشاهده
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Reports Tab -->
      <div v-if="activeTab === 'reports'" class="space-y-6">
        <h2 class="text-lg font-semibold text-gray-900">گزارش‌های مالی</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Trial Balance -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">تراز آزمایشی</h3>
            <p class="text-sm text-gray-600 mb-4">گزارش موجودی تمام حساب‌ها در یک تاریخ مشخص</p>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ</label>
                <input
                  v-model="reportDates.trialBalance"
                  type="date"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
              </div>
              <button
                @click="generateTrialBalance"
                :disabled="loading"
                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                تولید گزارش
              </button>
            </div>
          </div>

          <!-- Profit & Loss -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">صورت سود و زیان</h3>
            <p class="text-sm text-gray-600 mb-4">گزارش درآمدها و هزینه‌ها در یک دوره زمانی</p>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">از تاریخ</label>
                <input
                  v-model="reportDates.profitLoss.start"
                  type="date"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ</label>
                <input
                  v-model="reportDates.profitLoss.end"
                  type="date"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
              </div>
              <button
                @click="generateProfitLoss"
                :disabled="loading || !reportDates.profitLoss.start || !reportDates.profitLoss.end"
                class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
              >
                تولید گزارش
              </button>
            </div>
          </div>

          <!-- Balance Sheet -->
          <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">ترازنامه</h3>
            <p class="text-sm text-gray-600 mb-4">گزارش دارایی‌ها، بدهی‌ها و حقوق صاحبان سهام</p>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ</label>
                <input
                  v-model="reportDates.balanceSheet"
                  type="date"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
              </div>
              <button
                @click="generateBalanceSheet"
                :disabled="loading"
                class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
              >
                تولید گزارش
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Account Modal -->
    <div v-if="showCreateAccountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">ایجاد حساب جدید</h3>
          <form @submit.prevent="createAccount" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">نام حساب (فارسی)</label>
              <input
                v-model="newAccount.name"
                type="text"
                required
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">نام حساب (انگلیسی)</label>
              <input
                v-model="newAccount.name_en"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">نوع حساب</label>
              <select
                v-model="newAccount.type"
                required
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="">انتخاب کنید</option>
                <option value="asset">دارایی</option>
                <option value="liability">بدهی</option>
                <option value="equity">حقوق صاحبان سهام</option>
                <option value="revenue">درآمد</option>
                <option value="expense">هزینه</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ماهیت حساب</label>
              <select
                v-model="newAccount.normal_balance"
                required
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="">انتخاب کنید</option>
                <option value="debit">بدهکار</option>
                <option value="credit">بستانکار</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">موجودی اولیه</label>
              <input
                v-model.number="newAccount.opening_balance"
                type="number"
                step="0.01"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
            </div>
            <div class="flex justify-end space-x-3 space-x-reverse">
              <button
                type="button"
                @click="showCreateAccountModal = false"
                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                انصراف
              </button>
              <button
                type="submit"
                :disabled="loading"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                ایجاد حساب
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Create Journal Entry Modal -->
    <div v-if="showCreateJournalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">ایجاد سند حسابداری جدید</h3>
          <form @submit.prevent="createJournalEntry" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ سند</label>
                <input
                  v-model="newJournalEntry.entry_date"
                  type="date"
                  required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">مرجع</label>
                <input
                  v-model="newJournalEntry.reference"
                  type="text"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2"
                >
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">شرح سند</label>
              <textarea
                v-model="newJournalEntry.description"
                required
                rows="3"
                class="w-full border border-gray-300 rounded-lg px-3 py-2"
              ></textarea>
            </div>

            <!-- Journal Entry Details -->
            <div>
              <div class="flex items-center justify-between mb-4">
                <h4 class="text-md font-medium text-gray-900">ردیف‌های سند</h4>
                <button
                  type="button"
                  @click="addJournalDetail"
                  class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700"
                >
                  افزودن ردیف
                </button>
              </div>
              
              <div class="space-y-3">
                <div
                  v-for="(detail, index) in newJournalEntry.details"
                  :key="index"
                  class="grid grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg"
                >
                  <div class="col-span-4">
                    <select
                      v-model="detail.account_id"
                      required
                      class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
                    >
                      <option value="">انتخاب حساب</option>
                      <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </option>
                    </select>
                  </div>
                  <div class="col-span-3">
                    <input
                      v-model="detail.description"
                      type="text"
                      placeholder="شرح ردیف"
                      class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
                    >
                  </div>
                  <div class="col-span-2">
                    <input
                      v-model.number="detail.debit_amount"
                      type="number"
                      step="0.01"
                      placeholder="بدهکار"
                      @input="clearCredit(index)"
                      class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
                    >
                  </div>
                  <div class="col-span-2">
                    <input
                      v-model.number="detail.credit_amount"
                      type="number"
                      step="0.01"
                      placeholder="بستانکار"
                      @input="clearDebit(index)"
                      class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
                    >
                  </div>
                  <div class="col-span-1">
                    <button
                      type="button"
                      @click="removeJournalDetail(index)"
                      class="text-red-600 hover:text-red-800"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Totals -->
              <div class="mt-4 bg-blue-50 p-3 rounded-lg">
                <div class="grid grid-cols-3 gap-4 text-sm">
                  <div>
                    <span class="font-medium">مجموع بدهکار:</span>
                    <span class="mr-2">{{ formatCurrency(totalDebits) }}</span>
                  </div>
                  <div>
                    <span class="font-medium">مجموع بستانکار:</span>
                    <span class="mr-2">{{ formatCurrency(totalCredits) }}</span>
                  </div>
                  <div>
                    <span class="font-medium">تفاوت:</span>
                    <span :class="isBalanced ? 'text-green-600' : 'text-red-600'" class="mr-2">
                      {{ formatCurrency(Math.abs(totalDebits - totalCredits)) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end space-x-3 space-x-reverse">
              <button
                type="button"
                @click="showCreateJournalModal = false"
                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                انصراف
              </button>
              <button
                type="submit"
                :disabled="loading || !isBalanced || newJournalEntry.details.length < 2"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                ایجاد سند
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from '@/composables/useI18n'

const { formatCurrency, formatDate } = useI18n()

// State
const activeTab = ref('chart-of-accounts')
const loading = ref(false)
const accounts = ref([])
const journalEntries = ref({ data: [] })
const showCreateAccountModal = ref(false)
const showCreateJournalModal = ref(false)

// Filters
const accountsFilter = reactive({
  type: '',
  active_only: false
})

const journalFilter = reactive({
  status: '',
  start_date: '',
  end_date: ''
})

// Report dates
const reportDates = reactive({
  trialBalance: new Date().toISOString().split('T')[0],
  profitLoss: {
    start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  },
  balanceSheet: new Date().toISOString().split('T')[0]
})

// New account form
const newAccount = reactive({
  name: '',
  name_en: '',
  description: '',
  type: '',
  normal_balance: '',
  opening_balance: 0
})

// New journal entry form
const newJournalEntry = reactive({
  entry_date: new Date().toISOString().split('T')[0],
  description: '',
  reference: '',
  details: [
    { account_id: '', description: '', debit_amount: 0, credit_amount: 0 },
    { account_id: '', description: '', debit_amount: 0, credit_amount: 0 }
  ]
})

// Computed
const totalDebits = computed(() => {
  return newJournalEntry.details.reduce((sum, detail) => sum + (detail.debit_amount || 0), 0)
})

const totalCredits = computed(() => {
  return newJournalEntry.details.reduce((sum, detail) => sum + (detail.credit_amount || 0), 0)
})

const isBalanced = computed(() => {
  return Math.abs(totalDebits.value - totalCredits.value) < 0.01
})

// Methods
const loadAccounts = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (accountsFilter.type) params.append('type', accountsFilter.type)
    if (accountsFilter.active_only) params.append('active_only', 'true')

    const response = await fetch(`/api/accounting/chart-of-accounts?${params}`)
    const data = await response.json()
    
    if (data.success) {
      accounts.value = data.data
    }
  } catch (error) {
    console.error('Error loading accounts:', error)
  } finally {
    loading.value = false
  }
}

const loadJournalEntries = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (journalFilter.status) params.append('status', journalFilter.status)
    if (journalFilter.start_date) params.append('start_date', journalFilter.start_date)
    if (journalFilter.end_date) params.append('end_date', journalFilter.end_date)

    const response = await fetch(`/api/accounting/journal-entries?${params}`)
    const data = await response.json()
    
    if (data.success) {
      journalEntries.value = data.data
    }
  } catch (error) {
    console.error('Error loading journal entries:', error)
  } finally {
    loading.value = false
  }
}

const initializeChart = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/accounting/initialize-chart', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    
    const data = await response.json()
    
    if (data.success) {
      alert('دفتر حساب‌های استاندارد با موفقیت ایجاد شد')
      await loadAccounts()
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error initializing chart:', error)
    alert('خطا در ایجاد دفتر حساب‌ها')
  } finally {
    loading.value = false
  }
}

const createAccount = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/accounting/accounts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(newAccount)
    })
    
    const data = await response.json()
    
    if (data.success) {
      alert('حساب با موفقیت ایجاد شد')
      showCreateAccountModal.value = false
      Object.assign(newAccount, {
        name: '',
        name_en: '',
        description: '',
        type: '',
        normal_balance: '',
        opening_balance: 0
      })
      await loadAccounts()
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error creating account:', error)
    alert('خطا در ایجاد حساب')
  } finally {
    loading.value = false
  }
}

const createJournalEntry = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/accounting/journal-entries', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(newJournalEntry)
    })
    
    const data = await response.json()
    
    if (data.success) {
      alert('سند حسابداری با موفقیت ایجاد شد')
      showCreateJournalModal.value = false
      resetJournalForm()
      await loadJournalEntries()
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error creating journal entry:', error)
    alert('خطا در ایجاد سند')
  } finally {
    loading.value = false
  }
}

const postJournalEntry = async (entry) => {
  if (!confirm('آیا از ثبت این سند اطمینان دارید؟')) return
  
  loading.value = true
  try {
    const response = await fetch(`/api/accounting/journal-entries/${entry.id}/post`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    
    const data = await response.json()
    
    if (data.success) {
      alert('سند با موفقیت ثبت شد')
      await loadJournalEntries()
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error posting journal entry:', error)
    alert('خطا در ثبت سند')
  } finally {
    loading.value = false
  }
}

const reverseJournalEntry = async (entry) => {
  const reason = prompt('دلیل برگشت سند را وارد کنید:')
  if (!reason) return
  
  loading.value = true
  try {
    const response = await fetch(`/api/accounting/journal-entries/${entry.id}/reverse`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ reason })
    })
    
    const data = await response.json()
    
    if (data.success) {
      alert('سند با موفقیت برگشت خورد')
      await loadJournalEntries()
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error reversing journal entry:', error)
    alert('خطا در برگشت سند')
  } finally {
    loading.value = false
  }
}

const generateTrialBalance = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (reportDates.trialBalance) params.append('as_of_date', reportDates.trialBalance)

    const response = await fetch(`/api/accounting/reports/trial-balance?${params}`)
    const data = await response.json()
    
    if (data.success) {
      // Open report in new window or show modal
      console.log('Trial Balance:', data.data)
      alert('گزارش تراز آزمایشی تولید شد')
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error generating trial balance:', error)
    alert('خطا در تولید گزارش')
  } finally {
    loading.value = false
  }
}

const generateProfitLoss = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('start_date', reportDates.profitLoss.start)
    params.append('end_date', reportDates.profitLoss.end)

    const response = await fetch(`/api/accounting/reports/profit-loss?${params}`)
    const data = await response.json()
    
    if (data.success) {
      console.log('Profit & Loss:', data.data)
      alert('گزارش سود و زیان تولید شد')
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error generating profit & loss:', error)
    alert('خطا در تولید گزارش')
  } finally {
    loading.value = false
  }
}

const generateBalanceSheet = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (reportDates.balanceSheet) params.append('as_of_date', reportDates.balanceSheet)

    const response = await fetch(`/api/accounting/reports/balance-sheet?${params}`)
    const data = await response.json()
    
    if (data.success) {
      console.log('Balance Sheet:', data.data)
      alert('گزارش ترازنامه تولید شد')
    } else {
      alert(data.message)
    }
  } catch (error) {
    console.error('Error generating balance sheet:', error)
    alert('خطا در تولید گزارش')
  } finally {
    loading.value = false
  }
}

const addJournalDetail = () => {
  newJournalEntry.details.push({
    account_id: '',
    description: '',
    debit_amount: 0,
    credit_amount: 0
  })
}

const removeJournalDetail = (index) => {
  if (newJournalEntry.details.length > 2) {
    newJournalEntry.details.splice(index, 1)
  }
}

const clearCredit = (index) => {
  if (newJournalEntry.details[index].debit_amount > 0) {
    newJournalEntry.details[index].credit_amount = 0
  }
}

const clearDebit = (index) => {
  if (newJournalEntry.details[index].credit_amount > 0) {
    newJournalEntry.details[index].debit_amount = 0
  }
}

const resetJournalForm = () => {
  Object.assign(newJournalEntry, {
    entry_date: new Date().toISOString().split('T')[0],
    description: '',
    reference: '',
    details: [
      { account_id: '', description: '', debit_amount: 0, credit_amount: 0 },
      { account_id: '', description: '', debit_amount: 0, credit_amount: 0 }
    ]
  })
}

// Helper methods
const getAccountTypeClass = (type) => {
  const classes = {
    asset: 'bg-blue-100 text-blue-800',
    liability: 'bg-red-100 text-red-800',
    equity: 'bg-purple-100 text-purple-800',
    revenue: 'bg-green-100 text-green-800',
    expense: 'bg-orange-100 text-orange-800'
  }
  return classes[type] || 'bg-gray-100 text-gray-800'
}

const getAccountTypeLabel = (type) => {
  const labels = {
    asset: 'دارایی',
    liability: 'بدهی',
    equity: 'حقوق صاحبان سهام',
    revenue: 'درآمد',
    expense: 'هزینه'
  }
  return labels[type] || type
}

const getJournalStatusClass = (status) => {
  const classes = {
    draft: 'bg-yellow-100 text-yellow-800',
    posted: 'bg-green-100 text-green-800',
    reversed: 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getJournalStatusLabel = (status) => {
  const labels = {
    draft: 'پیش‌نویس',
    posted: 'ثبت شده',
    reversed: 'برگشت خورده'
  }
  return labels[status] || status
}

const editAccount = (account) => {
  // Implement edit functionality
  console.log('Edit account:', account)
}

const viewGeneralLedger = (account) => {
  // Implement general ledger view
  console.log('View general ledger for account:', account)
}

const viewJournalEntry = (entry) => {
  // Implement journal entry view
  console.log('View journal entry:', entry)
}

// Lifecycle
onMounted(() => {
  loadAccounts()
  if (activeTab.value === 'journal-entries') {
    loadJournalEntries()
  }
})
</script>