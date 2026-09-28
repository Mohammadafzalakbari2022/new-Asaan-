<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, useForm, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import {
  ArrowLeft,
  Copy,
  Check,
  Lock,
  Wallet,
  Gift,
  Users,
  Link2,
  ScrollText,
  Ban,
  UserPlus,
  SlidersHorizontal,
} from 'lucide-vue-next';

interface Person {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string | null;
  code: string;
  share_link: string;
  clicks: number;
  code_status: string;
}

interface Balances {
  available: number;
  locked: number;
  earned: number;
  spent: number;
}

interface ReferralRow {
  id: number;
  referred_name: string;
  referred_email: string | null;
  level: number;
  status: string;
  lifetime_spend: number;
  rewarded_at: string | null;
  created_at: string | null;
}

interface Commission {
  id: number;
  order_number: string | null;
  level: number;
  amount: number;
  status: string;
  unlocks_at: string | null;
  created_at: string | null;
}

interface LedgerEntry {
  id: number;
  type: string;
  amount: number;
  balance_after: number;
  reason: string | null;
  created_at: string | null;
}

const props = defineProps<{
  allowManualCredit: boolean;
  person: Person;
  balances: Balances;
  referredBy: { id: number; name: string; email: string } | null;
  referralsMade: ReferralRow[];
  commissions: Commission[];
  ledger: LedgerEntry[];
}>();

const { formatPrice } = useCurrency();

const copied = ref(false);

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(props.person.share_link);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {
    // The browser refused clipboard access. The link is on screen.
  }
};

const ledgerLabel: Record<string, string> = {
  earned: 'Reward earned',
  spent: 'Spent at checkout',
  reversed: 'Reward reversed',
  admin_credit: 'Added by staff',
  admin_debit: 'Taken by staff',
};

const reverseForm = useForm({ reason: '' });
const reversing = ref<number | null>(null);

const openReverse = (commission: Commission) => {
  reversing.value = reversing.value === commission.id ? null : commission.id;
  reverseForm.reason = '';
  reverseForm.clearErrors();
};

const submitReverse = (commission: Commission) => {
  reverseForm.post(`/admin/marketing/referrals/commissions/${commission.id}/reverse`, {
    preserveScroll: true,
    onSuccess: () => {
      reversing.value = null;
      reverseForm.reset();
    },
  });
};

const creditForm = useForm({
  user_id: String(props.person.id),
  type: 'admin_credit' as 'admin_credit' | 'admin_debit',
  amount: '',
  reason: '',
});

const submitCredit = () => {
  creditForm.transform((data) => ({
    ...data,
    user_id: Number(data.user_id),
    amount: Number(data.amount),
  })).post('/admin/marketing/referrals/credit', {
    preserveScroll: true,
    onSuccess: () => creditForm.reset('amount', 'reason'),
  });
};

// Taking more than the customer has is refused by the server, but the page
// should not offer a button that is guaranteed to fail.
const takingTooMuch = computed(
  () => creditForm.type === 'admin_debit' && Number(creditForm.amount || 0) > props.balances.available
);
</script>

<template>
  <Head :title="person.name" />

  <AdminLayout :title="person.name">
    <div class="p-6 space-y-6">
      <div>
        <Link
          href="/admin/marketing/referrals/people"
          class="inline-flex items-center text-xs font-semibold text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
        >
          <ArrowLeft class="w-3.5 h-3.5 mr-1" />
          Back to people
        </Link>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ person.name }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ person.email }}<span v-if="person.phone"> · {{ person.phone }}</span>
        </p>
        <p v-if="person.created_at" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
          Joined {{ person.created_at }}
        </p>
      </div>

      <div v-if="referredBy" class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-4">
        <p class="text-xs font-semibold text-purple-700 dark:text-purple-300 uppercase tracking-wider flex items-center gap-1.5">
          <UserPlus class="w-3.5 h-3.5" />
          Invited by
        </p>
        <p class="mt-1 text-sm text-purple-900 dark:text-purple-100">
          {{ referredBy.name }}
          <span class="text-purple-600 dark:text-purple-400">· {{ referredBy.email }}</span>
        </p>
      </div>

      <!-- Balances -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Available now</p>
          <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ formatPrice(balances.available) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Locked</p>
          <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
            <span class="inline-flex items-center">
              <Lock v-if="balances.locked > 0" class="w-5 h-5 mr-1.5" />
              {{ formatPrice(balances.locked) }}
            </span>
          </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Lifetime earned</p>
          <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatPrice(balances.earned) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Lifetime spent</p>
          <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatPrice(balances.spent) }}</p>
        </div>
      </div>

      <!-- Hand-issued credit -->
      <div v-if="allowManualCredit" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <SlidersHorizontal class="w-4 h-4 text-gray-400" />
          Adjust credit by hand
        </h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          For fixing mistakes and goodwill gestures. The customer sees the amount and your reason in their history.
        </p>

        <form @submit.prevent="submitCredit" class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
          <div class="md:col-span-3">
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              Action
            </label>
            <select
              v-model="creditForm.type"
              class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
            >
              <option value="admin_credit">Give credit</option>
              <option value="admin_debit">Take credit back</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label for="credit_amount" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              Amount
            </label>
            <input
              id="credit_amount"
              v-model="creditForm.amount"
              type="number"
              min="0.01"
              step="0.01"
              :class="[
                'w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 border rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 transition-all',
                creditForm.errors.amount || takingTooMuch
                  ? 'border-red-400 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                  : 'border-gray-200 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500/20',
              ]"
            />
            <p v-if="creditForm.errors.amount" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ creditForm.errors.amount }}
            </p>
            <p v-else-if="takingTooMuch" class="mt-1 text-xs text-red-600 dark:text-red-400">
              They only have {{ formatPrice(balances.available) }} available.
            </p>
          </div>

          <div class="md:col-span-5">
            <label for="credit_reason" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              Reason
            </label>
            <input
              id="credit_reason"
              v-model="creditForm.reason"
              type="text"
              placeholder="Why is this being done?"
              :class="[
                'w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 border rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 transition-all placeholder:text-gray-400',
                creditForm.errors.reason
                  ? 'border-red-400 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
                  : 'border-gray-200 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500/20',
              ]"
            />
            <p v-if="creditForm.errors.reason" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ creditForm.errors.reason }}
            </p>
          </div>

          <div class="md:col-span-2">
            <button
              type="submit"
              :disabled="creditForm.processing || takingTooMuch"
              class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold uppercase tracking-widest rounded-lg transition disabled:opacity-50"
            >
              {{ creditForm.processing ? 'Saving...' : 'Apply' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Code -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1.5">
              <Link2 class="w-3.5 h-3.5" />
              Share link
            </p>
            <p class="mt-2 font-mono text-sm text-gray-900 dark:text-white break-all">
              {{ person.share_link }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Code <span class="font-mono">{{ person.code }}</span> ·
              {{ person.clicks }} link opens ·
              <span :class="person.code_status === 'active' ? 'text-green-600 dark:text-green-400' : 'text-red-500'">
                {{ person.code_status === 'active' ? 'active' : 'disabled' }}
              </span>
            </p>
          </div>

          <button
            @click="copyLink"
            class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
          >
            <Check v-if="copied" class="w-4 h-4 mr-2" />
            <Copy v-else class="w-4 h-4 mr-2" />
            {{ copied ? 'Copied' : 'Copy link' }}
          </button>
        </div>

        <p v-if="person.code_status === 'disabled'" class="mt-3 text-xs text-amber-600 dark:text-amber-400">
          This code is disabled, so new visits with it are not credited to this customer.
        </p>
      </div>

      <!-- Invites -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
          <Users class="w-4 h-4 text-gray-400" />
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
            People they invited
            <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">({{ referralsMade.length }})</span>
          </h2>
        </div>

        <div v-if="referralsMade.length === 0" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
          Nobody yet.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Person</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lifetime spend</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in referralsMade" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/20">
                <td class="px-5 py-4">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">{{ row.referred_name }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ row.referred_email }}</div>
                </td>
                <td class="px-5 py-4 text-right text-sm text-gray-900 dark:text-white">
                  {{ formatPrice(row.lifetime_spend) }}
                </td>
                <td class="px-5 py-4">
                  <span
                    v-if="row.rewarded_at"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                  >
                    <Gift class="w-3 h-3" />
                    Rewarded
                  </span>
                  <span
                    v-else-if="row.status === 'voided'"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300"
                  >
                    <Ban class="w-3 h-3" />
                    Voided
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                  >
                    Still spending
                  </span>
                </td>
                <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">{{ row.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Commissions -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
          <Wallet class="w-4 h-4 text-gray-400" />
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
            Rewards granted
            <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">(last 50)</span>
          </h2>
        </div>

        <div v-if="commissions.length === 0" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
          No rewards granted yet.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Level</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <template v-for="c in commissions" :key="c.id">
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20">
                  <td class="px-5 py-4 text-sm text-gray-900 dark:text-white">
                    {{ c.order_number ?? '—' }}
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ c.created_at }}</div>
                  </td>
                  <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">L{{ c.level }}</td>
                  <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                    {{ formatPrice(c.amount) }}
                  </td>
                  <td class="px-5 py-4">
                    <span
                      v-if="c.status === 'active'"
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full"
                      :class="c.unlocks_at && new Date(c.unlocks_at) > new Date()
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                        : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'"
                    >
                      <Lock v-if="c.unlocks_at && new Date(c.unlocks_at) > new Date()" class="w-3 h-3" />
                      {{ c.unlocks_at && new Date(c.unlocks_at) > new Date() ? `Locked until ${c.unlocks_at}` : 'Available' }}
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300"
                    >
                      <Ban class="w-3 h-3" />
                      Reversed
                    </span>
                  </td>
                  <td class="px-5 py-4 text-right">
                    <button
                      v-if="c.status === 'active'"
                      @click="openReverse(c)"
                      class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline"
                    >
                      Reverse
                    </button>
                    <span v-else class="text-xs text-gray-400">—</span>
                  </td>
                </tr>

                <!-- Reverse reason form, under the row it belongs to -->
                <tr v-if="reversing === c.id" class="bg-red-50 dark:bg-red-900/10">
                  <td colspan="5" class="px-5 py-4">
                    <label class="block text-xs font-semibold text-red-800 dark:text-red-300 mb-1.5">
                      Why is this being taken back? The customer sees this in their history.
                    </label>
                    <input
                      v-model="reverseForm.reason"
                      type="text"
                      placeholder="e.g. The referred order was refunded"
                      class="w-full px-3 py-2 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500/20 focus:border-red-500"
                      @keyup.enter="submitReverse(c)"
                    />
                    <p v-if="reverseForm.errors.reason" class="mt-1 text-xs text-red-600 dark:text-red-400">
                      {{ reverseForm.errors.reason }}
                    </p>
                    <div class="mt-3 flex gap-2">
                      <button
                        @click="submitReverse(c)"
                        :disabled="reverseForm.processing"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50"
                      >
                        {{ reverseForm.processing ? 'Reversing...' : 'Take back' }}
                      </button>
                      <button
                        @click="reversing = null"
                        class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg"
                      >
                        Cancel
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Ledger -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
          <ScrollText class="w-4 h-4 text-gray-400" />
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
            Credit history
            <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">(last 50)</span>
          </h2>
        </div>

        <div v-if="ledger.length === 0" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
          No movements yet.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">When</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">What</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Change</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance after</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="entry in ledger" :key="entry.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/20">
                <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">{{ entry.created_at }}</td>
                <td class="px-5 py-4">
                  <div class="text-sm text-gray-900 dark:text-white">{{ ledgerLabel[entry.type] ?? entry.type }}</div>
                  <div v-if="entry.reason" class="text-xs text-gray-500 dark:text-gray-400">{{ entry.reason }}</div>
                </td>
                <td
                  class="px-5 py-4 text-right text-sm font-medium"
                  :class="entry.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                >
                  {{ entry.amount >= 0 ? '+' : '' }}{{ formatPrice(entry.amount) }}
                </td>
                <td class="px-5 py-4 text-right text-sm text-gray-900 dark:text-white">
                  {{ formatPrice(entry.balance_after) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
