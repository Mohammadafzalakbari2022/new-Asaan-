<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { Save, AlertTriangle, Info, Plus, Trash2 } from 'lucide-vue-next';

interface Settings {
  enabled: boolean;
  reward_amount: number;
  threshold_amount: number;
  lock_days: number;
  level_shares: number[];
  reward_mode: string;
  allow_admin_credit: boolean;
  block_account_deletion: boolean;
  credit_max_percent_of_order: number;
}

interface BreakdownRow {
  level: number;
  share: number;
  amount: number;
}

const props = defineProps<{
  settings: Settings;
  levelBreakdown: BreakdownRow[];
}>();

const { formatPrice } = useCurrency();

const form = useForm({
  enabled: props.settings.enabled,
  reward_amount: String(props.settings.reward_amount),
  threshold_amount: String(props.settings.threshold_amount),
  lock_days: String(props.settings.lock_days),
  level_shares: props.settings.level_shares.map((s) => String(s)),
  reward_mode: props.settings.reward_mode,
  allow_admin_credit: props.settings.allow_admin_credit,
  block_account_deletion: props.settings.block_account_deletion,
  credit_max_percent_of_order: String(props.settings.credit_max_percent_of_order),
});

watch(
  () => props.settings,
  (value) => {
    form.enabled = value.enabled;
    form.reward_amount = String(value.reward_amount);
    form.threshold_amount = String(value.threshold_amount);
    form.lock_days = String(value.lock_days);
    form.level_shares = value.level_shares.map((s) => String(s));
    form.reward_mode = value.reward_mode;
    form.allow_admin_credit = value.allow_admin_credit;
    form.block_account_deletion = value.block_account_deletion;
    form.credit_max_percent_of_order = String(value.credit_max_percent_of_order);
  }
);

const shareTotal = computed(() =>
  form.level_shares.reduce((sum, share) => sum + (Number(share) || 0), 0)
);

const isBalanced = computed(() => Math.abs(shareTotal.value - 100) < 0.001);

const preview = computed(() =>
  form.level_shares
    .map((share, i) => {
      const value = Number(share) || 0;
      const amount = ((Number(form.reward_amount) || 0) * value) / 100;
      return `L${i + 1}: ${value}% → ${formatPrice(Math.round(amount * 100) / 100)}`;
    })
);

const addLevel = () => {
  if (form.level_shares.length < 2) form.level_shares.push('0');
};

const removeLevel = (index: number) => {
  form.level_shares.splice(index, 1);
  form.level_shares = [...form.level_shares];
};

const save = () => {
  // The server is the real authority on the 100% total, but sending a clean
  // array here avoids a pointless validation round trip and the error message
  // that comes with it.
  form.transform((data) => ({
    ...data,
    reward_amount: Number(data.reward_amount),
    threshold_amount: Number(data.threshold_amount),
    lock_days: Number(data.lock_days),
    credit_max_percent_of_order: Number(data.credit_max_percent_of_order),
    level_shares: data.level_shares.map(Number),
  })).put('/admin/marketing/referrals/settings', {
    preserveScroll: true,
  });
};

const fieldClass = (hasError: boolean) =>
  [
    'w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700/50 border rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 transition-all',
    hasError
      ? 'border-red-400 dark:border-red-600 focus:border-red-500 focus:ring-red-500/20'
      : 'border-gray-200 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500/20',
  ].join(' ');

const errorFor = (key: keyof typeof form.errors) => form.errors[key];
</script>

<template>
  <Head title="Referral Settings" />

  <AdminLayout title="Referral Settings">
    <div class="p-6 max-w-4xl space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Referral settings</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          These numbers are what customers are promised. Changing them only affects rewards from now on.
        </p>
      </div>

      <form @submit.prevent="save" class="space-y-6">
        <!-- Switch on or off -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="form.enabled"
              type="checkbox"
              class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
            />
            <span>
              <span class="block text-sm font-semibold text-gray-900 dark:text-white">Programme switched on</span>
              <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Turning this off stops new rewards. Credit customers already have stays spendable.
              </span>
            </span>
          </label>
        </div>

        <!-- The reward -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 space-y-5">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">The reward</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label for="reward_amount" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                Reward per qualifying referral
              </label>
              <input
                id="reward_amount"
                v-model="form.reward_amount"
                type="number"
                min="0"
                step="0.01"
                :class="fieldClass(!!errorFor('reward_amount'))"
              />
              <p v-if="errorFor('reward_amount')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('reward_amount') }}
              </p>
              <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Store credit, not cash. Cannot be withdrawn.
              </p>
            </div>

            <div>
              <label for="threshold_amount" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                Spend needed to qualify
              </label>
              <input
                id="threshold_amount"
                v-model="form.threshold_amount"
                type="number"
                min="0"
                step="0.01"
                :class="fieldClass(!!errorFor('threshold_amount'))"
              />
              <p v-if="errorFor('threshold_amount')" class="mt-1 text-xs text-red-600 dark:text-red-400">
                {{ errorFor('threshold_amount') }}
              </p>
              <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Lifetime spend of the invited person. Shipping is not counted.
              </p>
            </div>
          </div>

          <div>
            <label for="reward_mode" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              How often
            </label>
            <select
              id="reward_mode"
              v-model="form.reward_mode"
              :class="fieldClass(!!errorFor('reward_mode'))"
            >
              <option value="once_per_person">Once per person</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Each person you invite pays you once, no matter how many times they order.
            </p>
          </div>
        </div>

        <!-- Levels -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Level split</h2>
            <button
              v-if="form.level_shares.length < 2"
              type="button"
              @click="addLevel"
              class="inline-flex items-center text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
            >
              <Plus class="w-3.5 h-3.5 mr-1" />
              Add a second level
            </button>
          </div>

          <div
            v-for="(share, i) in form.level_shares"
            :key="i"
            class="flex items-end gap-3"
          >
            <div class="w-24">
              <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                Level {{ i + 1 }}
              </label>
              <div class="relative">
                <input
                  v-model="form.level_shares[i]"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  :class="fieldClass(false)"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
              </div>
            </div>

            <div class="flex-1 pb-2.5">
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Pays {{ formatPrice(((Number(form.reward_amount) || 0) * (Number(share) || 0)) / 100) }} per qualifying referral
              </p>
            </div>

            <button
              v-if="form.level_shares.length > 1"
              type="button"
              @click="removeLevel(i)"
              class="p-2 mb-1 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>

          <p
            v-if="!isBalanced"
            class="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400"
          >
            <AlertTriangle class="w-3.5 h-3.5" />
            These add up to {{ shareTotal }}%. They must add up to 100%. They will be rescaled to 100% when saved.
          </p>
          <p v-else-if="errorFor('level_shares')" class="text-xs text-red-600 dark:text-red-400">
            {{ errorFor('level_shares') }}
          </p>
          <p v-else class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <Info class="w-3.5 h-3.5" />
            {{ preview.join('   ·   ') }}
          </p>
        </div>

        <!-- Checkout & safety -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 space-y-5">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">At checkout</h2>

          <div>
            <label for="credit_max_percent_of_order" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
              Most of one order that credit can cover
            </label>
            <div class="relative">
              <input
                id="credit_max_percent_of_order"
                v-model="form.credit_max_percent_of_order"
                type="number"
                min="0"
                max="100"
                :class="[fieldClass(!!errorFor('credit_max_percent_of_order')), 'pr-10']"
              />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
            </div>
            <p v-if="errorFor('credit_max_percent_of_order')" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ errorFor('credit_max_percent_of_order') }}
            </p>
            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Credit can never pay for delivery, and never takes an order below zero.
            </p>
          </div>
        </div>

        <!-- Admin actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 space-y-4">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Staff powers and account safety</h2>

          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="form.allow_admin_credit"
              type="checkbox"
              class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
            />
            <span>
              <span class="block text-sm font-semibold text-gray-900 dark:text-white">Allow staff to add or take credit by hand</span>
              <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Every hand change is written into the customer's history with a reason.
              </span>
            </span>
          </label>

          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="form.block_account_deletion"
              type="checkbox"
              class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
            />
            <span>
              <span class="block text-sm font-semibold text-gray-900 dark:text-white">Block account deletion while credit is owed</span>
              <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                A customer with unspent credit must spend it before the account can be deleted.
              </span>
            </span>
          </label>
        </div>

        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center px-5 py-2.5 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-50"
          >
            <Save class="w-4 h-4 mr-2" />
            {{ form.processing ? 'Saving...' : 'Save settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
