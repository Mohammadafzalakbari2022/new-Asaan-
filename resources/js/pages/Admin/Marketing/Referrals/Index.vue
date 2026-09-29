<script setup lang="ts">
import { computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useI18nStore } from '@/Stores/i18n';
import {
  Users,
  Gift,
  Wallet,
  Lock,
  TrendingUp,
  Settings,
  Crown,
  UserCheck,
  AlertTriangle,
  CheckCircle2,
  ArrowRight,
} from 'lucide-vue-next';

interface Stats {
  enabled: boolean;
  reward_amount: number;
  threshold_amount: number;
  lock_days: number;
  level_breakdown: { level: number; share: number; amount: number }[];
  codes_issued: number;
  programme_members: number;
  total_referrals: number;
  referrals_rewarded: number;
  referrals_pending: number;
  conversion_rate: number;
  total_credit_generated: number;
  locked_credit: number;
  credit_spent: number;
  outstanding_liability: number;
  avg_lifetime_spend_of_referred: number;
}

interface TopReferrer {
  user_id: number;
  name: string;
  email: string | null;
  total_earned: number;
  awards: number;
  last_award_at: string | null;
  available: number;
  locked: number;
}

interface TrendPoint {
  day: string;
  total: number;
}

const props = defineProps<{
  stats: Stats;
  trend: TrendPoint[];
  topReferrers: TopReferrer[];
}>();

const { formatPrice } = useCurrency();
const { t } = useI18nStore();

// The chart is drawn with plain divs rather than a charting library, so it works
// with no extra dependency and degrades to a readable bar list.
const peak = computed(() => Math.max(...props.trend.map((p) => p.total), 1));

const bars = computed(() =>
  props.trend.map((point) => ({
    ...point,
    height: Math.max(2, Math.round((point.total / peak.value) * 100)),
  }))
);

const rulesSummary = computed(() =>
  props.stats.level_breakdown
    .map((row) => t('L{level} {share}% ({amount})', { level: row.level, share: row.share, amount: formatPrice(row.amount) }))
    .join('  ·  ')
);
</script>

<template>
  <Head :title="$t('Referral Programme')" />

  <AdminLayout :title="$t('Referrals')">
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Referral Programme') }}</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('How the programme is performing, and what the store is currently promising customers.') }}
          </p>
        </div>

        <div class="flex items-center gap-2">
          <span
            v-if="stats.enabled"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
          >
            <CheckCircle2 class="w-4 h-4" />
            {{ $t('Live') }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
          >
            <AlertTriangle class="w-4 h-4" />
            {{ $t('Switched off') }}
          </span>

          <Link
            href="/admin/marketing/referrals/settings"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            <Settings class="w-4 h-4 mr-2" />
            {{ $t('Settings') }}
          </Link>
        </div>
      </div>

      <!-- The promise the shop is making right now -->
      <div
        v-if="stats.enabled"
        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-5"
      >
        <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wider">
          {{ $t('What customers are being offered') }}
        </p>
        <p class="mt-2 text-sm text-blue-900 dark:text-blue-100">
          {{
            $t('{reward} of store credit per qualifying referral, once the person you invited has spent {threshold}. Credit unlocks after {days} days.', {
              reward: formatPrice(stats.reward_amount),
              threshold: formatPrice(stats.threshold_amount),
              days: stats.lock_days,
            })
          }}
        </p>
        <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">{{ rulesSummary }}</p>
      </div>

      <!-- Money owed to customers -->
      <div>
        <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
          {{ $t('What the store owes') }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Outstanding liability') }}</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatPrice(stats.outstanding_liability) }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('Credit earned that has not been spent yet') }}
            </p>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Still locked') }}</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
              {{ formatPrice(stats.locked_credit) }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('Cannot be spent until it unlocks') }}</p>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Spent so far') }}</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">
              {{ formatPrice(stats.credit_spent) }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('Credit customers have actually used') }}</p>
          </div>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          {{
            $t('Total credit ever generated: {total}. Outstanding liability is what is still walkable, so it is the number to budget for.', {
              total: formatPrice(stats.total_credit_generated),
            })
          }}
        </p>
      </div>

      <!-- People -->
      <div>
        <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
          {{ $t('People') }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Members') }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.programme_members }}</p>
              </div>
              <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <Users class="w-6 h-6 text-blue-600 dark:text-blue-400" />
              </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $t('{count} codes issued', { count: stats.codes_issued }) }}</p>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Invites') }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_referrals }}</p>
              </div>
              <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                <UserCheck class="w-6 h-6 text-purple-600 dark:text-purple-400" />
              </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $t('{count} not yet qualified', { count: stats.referrals_pending }) }}</p>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Rewarded') }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.referrals_rewarded }}</p>
              </div>
              <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <Gift class="w-6 h-6 text-green-600 dark:text-green-400" />
              </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('{percent}% of invites paid the threshold', { percent: stats.conversion_rate }) }}
            </p>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('Average spend') }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                  {{ formatPrice(stats.avg_lifetime_spend_of_referred) }}
                </p>
              </div>
              <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                <TrendingUp class="w-6 h-6 text-orange-600 dark:text-orange-400" />
              </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $t('Lifetime, of people who qualified') }}</p>
          </div>
        </div>
      </div>

      <!-- 30 day trend -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('Credit handed out, last 30 days') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          {{ $t('One bar per day. Flat days are days with no rewards.') }}
        </p>

        <div class="mt-5 flex items-end gap-[3px] h-32">
          <div
            v-for="(point, i) in bars"
            :key="point.day"
            class="flex-1 rounded-t bg-blue-500 dark:bg-blue-400 min-h-[2px] transition-colors"
            :class="point.total === 0 ? 'bg-gray-200 dark:bg-gray-700' : 'hover:bg-blue-600'"
            :style="{ height: point.height + '%' }"
            :title="$t('{day}: {amount}', { day: point.day, amount: formatPrice(point.total) })"
          />
        </div>

        <div class="mt-2 flex justify-between text-[10px] text-gray-400 dark:text-gray-500">
          <span>{{ bars[0]?.day }}</span>
          <span>{{ bars[bars.length - 1]?.day }}</span>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
          {{ $t('Peak day: {amount}.', { amount: formatPrice(peak) }) }}
        </p>
      </div>

      <!-- Top referrers -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('Top earners') }}</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $t('Ranked by credit earned') }}</p>
          </div>
          <Link
            href="/admin/marketing/referrals/top-referrers"
            class="inline-flex items-center text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
          >
            {{ $t('See all') }}
            <ArrowRight class="w-3.5 h-3.5 ml-1" />
          </Link>
        </div>

        <div v-if="topReferrers.length === 0" class="px-5 py-10 text-center">
          <Crown class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600" />
          <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            {{ $t('Nobody has earned a reward yet. Once someone does, they show up here.') }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Customer') }}</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Earned') }}</th>
                <th class="hidden sm:table-cell px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Awards') }}</th>
                <th class="hidden sm:table-cell px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Available') }}</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('Locked') }}</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="(person, i) in topReferrers" :key="person.user_id" class="hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors">
                <td class="px-5 py-4 text-sm font-medium text-gray-500 dark:text-gray-400">{{ i + 1 }}</td>
                <td class="px-5 py-4">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">{{ person.name }}</div>
                  <div v-if="person.email" class="text-xs text-gray-500 dark:text-gray-400">{{ person.email }}</div>
                </td>
                <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                  {{ formatPrice(person.total_earned) }}
                </td>
                <td class="hidden sm:table-cell px-5 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                  {{ person.awards }}
                </td>
                <td class="hidden sm:table-cell px-5 py-4 text-right text-sm text-green-600 dark:text-green-400">
                  {{ formatPrice(person.available) }}
                </td>
                <td class="px-5 py-4 text-right text-sm text-amber-600 dark:text-amber-400">
                  <span class="inline-flex items-center">
                    <Lock v-if="person.locked > 0" class="w-3 h-3 mr-1" />
                    {{ formatPrice(person.locked) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-right">
                  <Link
                    :href="`/admin/marketing/referrals/people/${person.user_id}`"
                    class="text-blue-600 dark:text-blue-400 hover:underline text-xs font-semibold"
                  >
                    {{ $t('Open') }}
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
