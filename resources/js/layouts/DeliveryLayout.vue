<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { LayoutDashboard, Truck, Wrench, LogOut } from 'lucide-vue-next'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const page = usePage<{ auth: { user: { name?: string } | null } }>()
const user = computed(() => page.props.auth.user)

interface NavItem {
  label: string
  href: string
  icon: any
  match: string
}

const navItems: NavItem[] = [
  { label: 'Dashboard', href: '/delivery', icon: LayoutDashboard, match: '/delivery' },
  { label: 'Deliveries', href: '/delivery/deliveries', icon: Truck, match: '/delivery/deliveries' },
  { label: 'My Jobs', href: '/delivery/jobs', icon: Wrench, match: '/delivery/jobs' },
]

/*
 * The tab you are on is the one that lights up. The dashboard is the fallback,
 * so it is only active when nothing more specific is.
 */
const currentPath = computed(() => page.url.split('?')[0].replace(/\/$/, ''))
const isActive = (item: NavItem) => {
  if (item.match === '/delivery') {
    return currentPath.value === '/delivery' || currentPath.value === ''
  }

  return currentPath.value.startsWith(item.match)
}

const logout = () => {
  router.post('/delivery/logout')
}
</script>

<template>
  <div class="min-h-screen bg-muted/40">
    <!-- Header -->
    <header class="sticky top-0 z-40 border-b border-border bg-background">
      <div class="mx-auto max-w-5xl px-4 h-14 flex items-center justify-between">
        <Link href="/delivery" class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-primary text-primary-foreground flex items-center justify-center">
            <Truck class="w-[18px] h-[18px]" />
          </div>
          <span class="text-sm font-semibold text-foreground">Asaan Delivery</span>
        </Link>

        <div class="flex items-center gap-3">
          <LanguageSwitcher />
          <div class="text-right leading-tight hidden sm:block">
            <p class="text-xs font-medium text-foreground truncate max-w-[140px]">{{ user?.name }}</p>
            <p class="text-[11px] text-muted-foreground">{{ $t('Delivery Person') }}</p>
          </div>
          <button
            @click="logout"
            class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
            :title="$t('Log out')"
          >
            <LogOut class="w-4 h-4" />
            <span class="hidden sm:inline">{{ $t('Log out') }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Nav -->
    <nav class="border-b border-border bg-background">
      <div class="mx-auto max-w-5xl px-4">
        <div class="flex gap-1 py-2">
          <template v-for="item in navItems" :key="item.href">
            <Link
              :href="item.href"
              class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium rounded-lg transition-colors"
              :class="isActive(item)
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:text-foreground hover:bg-muted'"
            >
              <component :is="item.icon" class="w-4 h-4" />
              <span>{{ $t(item.label) }}</span>
            </Link>
          </template>
        </div>
      </div>
    </nav>

    <!-- Content -->
    <main class="mx-auto max-w-5xl px-4 py-6">
      <slot />
    </main>
  </div>
</template>