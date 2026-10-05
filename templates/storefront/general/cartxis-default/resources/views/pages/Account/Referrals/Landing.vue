<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { Gift, Wallet, Clock, ArrowRight, ShieldCheck } from 'lucide-vue-next';

interface Props {
  code: string | null;
  programme: {
    reward_amount: number;
    threshold_amount: number;
    lock_days: number;
  };
}

const props = defineProps<Props>();

const { formatPrice } = useCurrency();

const signupUrl = computed(() =>
  props.code ? `/account/register?ref=${encodeURIComponent(props.code)}` : '/account/register'
);

const loginUrl = computed(() =>
  props.code ? `/account/login?ref=${encodeURIComponent(props.code)}` : '/account/login'
);

// The wording lives in the template because $t is only available there. The keys
// here just pick the icon.
const perks = [
  { key: 'reward', icon: Gift },
  { key: 'credit', icon: Wallet },
  { key: 'unlock', icon: Clock },
];
</script>

<template>
  <Head :title="$t('You have been invited')" />

  <ThemeLayout>
    <div class="container mx-auto px-4 py-14 max-w-3xl">
      <!-- What this is -->
      <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-blue-50 mb-5">
          <Gift class="w-7 h-7 text-blue-600" />
        </div>

        <h1 class="text-3xl font-bold mb-3">
          {{ code ? $t('Someone wants to shop with us') : $t('Shop with us') }}
        </h1>

        <p class="text-lg text-gray-600 dark:text-slate-400 max-w-2xl mx-auto">
          {{
            code
              ? $t('Use this link to create your account. Your friend earns {reward} of store credit once you have spent {threshold}.', {
                  reward: formatPrice(programme.reward_amount),
                  threshold: formatPrice(programme.threshold_amount),
                })
              : $t('Our referral programme rewards customers who bring their friends.')
          }}
        </p>

        <div v-if="code" class="mt-6 inline-flex items-center gap-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg px-5 py-3">
          <span class="text-sm text-gray-600 dark:text-slate-400">{{ $t('Their code') }}</span>
          <span class="font-mono text-lg font-bold tracking-wider">{{ code }}</span>
        </div>
      </div>

      <!-- What is included -->
      <div class="bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6 mb-8">
        <h2 class="text-lg font-semibold mb-5">{{ $t('What you get') }}</h2>

        <div class="space-y-5">
          <div v-for="perk in perks" :key="perk.key" class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
              <component :is="perk.icon" class="w-5 h-5 text-blue-600" />
            </div>
            <div v-if="perk.key === 'reward'">
              <!-- Only the person who sent the link earns. The friend is not
                   promised anything, so this must not imply they are. -->
              <h3 class="font-semibold text-sm mb-1">{{ $t('They start earning your credit') }}</h3>
              <p class="text-sm text-gray-600 dark:text-slate-400">
                {{
                  $t('Once you have spent {threshold} with us, your friend earns {reward} of store credit for bringing you.', {
                    threshold: formatPrice(programme.threshold_amount),
                    reward: formatPrice(programme.reward_amount),
                  })
                }}
              </p>
            </div>
            <div v-else-if="perk.key === 'credit'">
              <h3 class="font-semibold text-sm mb-1">{{ $t('Credit, not cash') }}</h3>
              <p class="text-sm text-gray-600 dark:text-slate-400">
                {{ $t('It can be spent on anything in the store, and cannot be withdrawn as money.') }}
              </p>
            </div>
            <div v-else>
              <h3 class="font-semibold text-sm mb-1">{{ $t('Locked, then theirs') }}</h3>
              <p class="text-sm text-gray-600 dark:text-slate-400">
                {{
                  $t('The credit unlocks {days} days after the order, so a returned order never costs them the reward.', {
                    days: programme.lock_days,
                  })
                }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- The catch, stated plainly -->
      <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 mb-8">
        <h2 class="text-sm font-semibold text-amber-900 mb-2 flex items-center gap-2">
          <ShieldCheck class="w-4 h-4" />
          {{ $t('The one rule to know') }}
        </h2>
        <p class="text-sm text-amber-800">
          {{
            $t('The reward is only paid once your friend has spent {threshold} with us, counted across all their orders. The link must be used while they are creating the account.', {
              threshold: formatPrice(programme.threshold_amount),
            })
          }}
        </p>
      </div>

      <!-- Call to action -->
      <div class="text-center">
        <Link
          :href="signupUrl"
          class="inline-flex items-center px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition"
        >
          {{ $t('Create your account') }}
          <ArrowRight class="w-4 h-4 ml-2" />
        </Link>

        <p class="mt-4 text-sm text-gray-500 dark:text-slate-400">
          {{ $t('Already have an account?') }}
          <Link :href="loginUrl" class="text-blue-600 hover:underline">
            {{ $t('Sign in') }}
          </Link>
        </p>
      </div>
    </div>
  </ThemeLayout>
</template>
