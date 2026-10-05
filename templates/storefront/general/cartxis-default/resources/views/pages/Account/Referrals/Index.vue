<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import {
  Copy,
  Check,
  Wallet,
  Lock,
  Gift,
  Users,
  Share2,
  Clock,
  Info,
} from 'lucide-vue-next';

interface Referral {
  code: string;
  share_link: string;
  share_text: string;
  clicks: number;
  status: string;
}

interface Balances {
  available: number;
  locked: number;
  earned: number;
  spent: number;
}

interface Invited {
  id: number;
  name: string;
  lifetime_spend: number;
  rewarded: boolean;
  status: string;
  joined_on: string | null;
}

interface Props {
  referral: Referral;
  balances: Balances;
  programme: {
    reward_amount: number;
    threshold_amount: number;
    lock_days: number;
    credit_max_percent_of_order: number;
    spends_excluding_shipping: boolean;
  };
  invited: Invited[];
  invited_count: number;
  rewarded_count: number;
  referredBy: { name: string } | null;
}

const props = defineProps<Props>();

const { formatPrice } = useCurrency();

const copied = ref<'code' | 'link' | 'message' | null>(null);

const copy = async (what: 'code' | 'link' | 'message', text: string) => {
  try {
    if (what === 'message' && navigator.share) {
      await navigator.share({ text, url: props.referral.share_link });
      return;
    }

    await navigator.clipboard.writeText(text);
    copied.value = what;
    setTimeout(() => {
      if (copied.value === what) copied.value = null;
    }, 2000);
  } catch {
    // The browser refused, or the person cancelled the share sheet. The text is
    // on screen to select by hand.
  }
};

// Only 50 invites are sent to the page, but the true total is passed separately.
const showingAll = computed(() => props.invited.length >= props.invited_count);
</script>

<template>
  <Head :title="$t('Refer & Earn')" />

  <ThemeLayout>
    <div class="container mx-auto px-4 py-10 max-w-5xl">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">{{ $t('Refer & Earn') }}</h1>
        <p class="text-gray-600 dark:text-slate-400">
          {{
            $t('Invite a friend and earn {reward} of store credit once they have spent {threshold}.', {
              reward: formatPrice(programme.reward_amount),
              threshold: formatPrice(programme.threshold_amount),
            })
          }}
        </p>
        <p v-if="referredBy" class="mt-2 text-sm text-purple-700">
          {{ $t('You were invited by {name}.', { name: referredBy.name }) }}
        </p>
      </div>

      <div v-if="referral.status === 'disabled'" class="mb-6 bg-amber-50 border border-amber-200 rounded-lg p-4">
        <p class="text-sm text-amber-900">
          {{ $t('Your referral code is not active at the moment. Rewards you already have still work.') }}
        </p>
      </div>

      <!-- Credit -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-5">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400">
            <Wallet class="w-4 h-4 text-green-600" />
            {{ $t('Available to spend') }}
          </div>
          <p class="mt-2 text-2xl font-bold text-green-600">{{ formatPrice(balances.available) }}</p>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
            {{ $t('Use it at checkout, up to {percent}% of an order.', { percent: programme.credit_max_percent_of_order }) }}
          </p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-5">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400">
            <Lock class="w-4 h-4 text-amber-600" />
            {{ $t('Locked') }}
          </div>
          <p class="mt-2 text-2xl font-bold text-amber-600">{{ formatPrice(balances.locked) }}</p>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
            {{ $t('Unlocks {days} days after the order, so a returned order cannot cost you the reward.', { days: programme.lock_days }) }}
          </p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-5">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400">
            <Gift class="w-4 h-4 text-blue-600" />
            {{ $t('Lifetime earned') }}
          </div>
          <p class="mt-2 text-2xl font-bold">{{ formatPrice(balances.earned) }}</p>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ formatPrice(balances.spent) }} {{ $t('spent so far') }}</p>
        </div>
      </div>

      <!-- Share -->
      <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
          <Share2 class="w-5 h-5 text-blue-600" />
          {{ $t('Share your link') }}
        </h2>

        <div class="mb-5">
          <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
            {{ $t('Your code') }}
          </label>
          <div class="flex items-center gap-3">
            <span class="font-mono text-xl font-bold tracking-wider">{{ referral.code }}</span>
            <button
              @click="copy('code', referral.code)"
              class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800 transition"
            >
              <Check v-if="copied === 'code'" class="w-3.5 h-3.5 mr-1.5 text-green-600" />
              <Copy v-else class="w-3.5 h-3.5 mr-1.5" />
              {{ copied === 'code' ? $t('Copied') : $t('Copy') }}
            </button>
          </div>
        </div>

        <div class="mb-5">
          <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
            {{ $t('Your link') }}
          </label>
          <div class="flex flex-col sm:flex-row gap-2">
            <input
              :value="referral.share_link"
              readonly
              class="flex-1 px-3 py-2.5 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm font-mono text-gray-700 dark:text-slate-300"
              @focus="($event.target as HTMLInputElement).select()"
            />
            <button
              @click="copy('link', referral.share_link)"
              class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition whitespace-nowrap"
            >
              <Check v-if="copied === 'link'" class="w-4 h-4 mr-2" />
              <Copy v-else class="w-4 h-4 mr-2" />
              {{ copied === 'link' ? $t('Copied') : $t('Copy link') }}
            </button>
          </div>
          <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">
            {{ $t('Anyone who signs up with this link is linked to you automatically.') }}
          </p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">
            {{ $t('Ready-made message') }}
          </label>
          <p class="px-3 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm text-gray-700 dark:text-slate-300 mb-2">
            {{ referral.share_text }}
          </p>
          <button
            @click="copy('message', referral.share_text)"
            class="inline-flex items-center px-4 py-2.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold rounded-lg transition"
          >
            <Check v-if="copied === 'message'" class="w-4 h-4 mr-2" />
            <Share2 v-else class="w-4 h-4 mr-2" />
            {{ copied === 'message' ? $t('Copied') : $t('Share') }}
          </button>
        </div>
      </div>

      <!-- Invites -->
      <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <Users class="w-5 h-5 text-gray-400 dark:text-slate-500" />
            {{ $t('People you invited') }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-slate-400">
            {{ $t('{awarded} of {total} earned', {
              awarded: rewarded_count,
              total: invited_count,
            }) }}
          </p>
        </div>

        <div v-if="invited.length === 0" class="px-6 py-10 text-center">
          <Users class="w-8 h-8 mx-auto text-gray-300" />
          <p class="mt-3 text-sm text-gray-500 dark:text-slate-400">
            {{ $t('Nobody yet. Share your link to get started.') }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                  {{ $t('Person') }}
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                  {{ $t('Their spend') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                  {{ $t('Status') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                  {{ $t('Joined') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
              <tr v-for="person in invited" :key="person.id" class="hover:bg-gray-50 dark:hover:bg-slate-800">
                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-slate-100">{{ person.name }}</td>
                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-slate-300">
                  {{ formatPrice(person.lifetime_spend) }}
                  <div class="text-[10px] text-gray-400 dark:text-slate-500">
                    {{ $t('needs {amount} to qualify', { amount: formatPrice(programme.threshold_amount) }) }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span
                    v-if="person.rewarded"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800"
                  >
                    <Gift class="w-3 h-3" />
                    {{ $t('Reward earned') }}
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800"
                  >
                    <Clock class="w-3 h-3" />
                    {{ $t('Still spending') }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">{{ person.joined_on }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="invited.length > 0" class="px-6 py-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900">
          <p class="text-xs text-gray-500 dark:text-slate-400 flex items-start gap-1.5">
            <Info class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" />
            <span v-if="showingAll">
              {{ $t('This is everyone you have invited.') }}
            </span>
            <span v-else>
              {{ $t('Showing the {shown} most recent of {total}.', { shown: invited.length, total: invited_count }) }}
            </span>
          </p>
        </div>
      </div>

      <!-- Rules -->
      <div class="mt-8 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-5">
        <h2 class="text-sm font-semibold mb-2">{{ $t('How it works') }}</h2>
        <ul class="text-sm text-gray-600 dark:text-slate-400 space-y-1.5 list-disc list-inside">
          <li>
            {{ $t('You earn {reward} once per person, after they have spent {threshold}.', {
              reward: formatPrice(programme.reward_amount),
              threshold: formatPrice(programme.threshold_amount),
            }) }}
          </li>
          <li>{{ $t('Their spend is counted over their whole time with us, not just their first order.') }}</li>
          <li>{{ $t('Reward credit is held for {days} days, so a returned order cannot cost you the reward.', { days: programme.lock_days }) }}</li>
          <li>{{ $t('Credit is for shopping only. It cannot be taken out as cash.') }}</li>
          <li>{{ $t('You choose at checkout how much credit to use, up to {percent}% of the order.', { percent: programme.credit_max_percent_of_order }) }}</li>
        </ul>
      </div>
    </div>
  </ThemeLayout>
</template>
