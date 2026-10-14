<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useAuthStore } from '@/stores/authStore'
import { UNION_NAME, avatarUrl as toAvatarUrl } from '@/utils/avatarUrl'

const router = useRouter()
const authStore = useAuthStore()

const userData = computed(() => authStore.user)

const avatarUrl = computed(() => toAvatarUrl((userData.value as any)?.avatar))

const logout = async () => {
  await authStore.revokeSession()
  router.push({ name: 'admin-login' })
}

const landingUrl = (import.meta.env.VITE_LANDING_URL || 'http://localhost:8080').replace(/\/$/, '')

const isStudent = computed(() => userData.value?.role === 'student')

const displayName = computed(() => {
  const name = userData.value?.fullName || userData.value?.name || ''
  if (userData.value?.role === 'admin' && (!name.trim() || /^admin$/i.test(name.trim())))
    return UNION_NAME
  return name
})

const displayRole = computed(() => {
  const map: Record<string, string> = {
    admin: 'مدير النظام',
    accountant: 'محاسب',
    supervisor: 'مشرف',
    student: 'عضو',
    instructor: 'مشرف',
  }
  return map[userData.value?.role ?? ''] ?? userData.value?.role ?? ''
})

const userProfileList = computed(() => {
  if (isStudent.value) {
    return [
      { type: 'divider' },
      { type: 'navItem', icon: 'tabler-layout-dashboard', title: 'لوحتي', href: `${landingUrl}/student/dashboard` },
      { type: 'divider' },
    ]
  }
  return [
    { type: 'divider' },
    { type: 'navItem', icon: 'tabler-user-circle', title: 'الملف الشخصي', to: { name: 'profile' } },
    { type: 'navItem', icon: 'tabler-layout-dashboard', title: 'لوحة التحكم', to: { name: 'dashboards' } },
    { type: 'divider' },
  ]
})
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar
      size="38"
      class="cursor-pointer"
      :color="!(userData && userData.avatar) ? 'primary' : undefined"
      :variant="!(userData && userData.avatar) ? 'tonal' : undefined"
    >
      <VImg
        v-if="userData && userData.avatar"
        :src="avatarUrl"
      />
      <VIcon
        v-else
        icon="tabler-user"
      />

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="240"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <div class="d-flex gap-2 align-center">
              <VListItemAction>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                  bordered
                >
                  <VAvatar
                    :color="!(userData && userData.avatar) ? 'primary' : undefined"
                    :variant="!(userData && userData.avatar) ? 'tonal' : undefined"
                  >
                    <VImg
                      v-if="userData && userData.avatar"
                      :src="avatarUrl"
                    />
                    <VIcon
                      v-else
                      icon="tabler-user"
                    />
                  </VAvatar>
                </VBadge>
              </VListItemAction>

              <div>
                <h6 class="text-h6 font-weight-medium" style="font-family:Cairo,sans-serif">
                  {{ displayName }}
                </h6>
                <VListItemSubtitle class="text-disabled" style="font-family:Cairo,sans-serif">
                  {{ displayRole }}
                </VListItemSubtitle>
              </div>
            </div>
          </VListItem>

          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <template
              v-for="item in userProfileList"
              :key="item.title"
            >
              <VListItem
                v-if="item.type === 'navItem'"
                :to="item.to"
                :href="item.href"
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                </template>

                <VListItemTitle>{{ item.title }}</VListItemTitle>

                <template
                  v-if="item.badgeProps"
                  #append
                >
                  <VBadge
                    rounded="sm"
                    class="me-3"
                    v-bind="item.badgeProps"
                  />
                </template>
              </VListItem>

              <VDivider
                v-else
                class="my-2"
              />
            </template>

            <div class="px-4 py-2">
              <VBtn
                block
                size="small"
                color="error"
                append-icon="tabler-logout"
                style="font-family:Cairo,sans-serif"
                @click="logout"
              >
                تسجيل الخروج
              </VBtn>
            </div>
          </PerfectScrollbar>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
