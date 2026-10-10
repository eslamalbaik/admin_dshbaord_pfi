<script lang="ts" setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import type { Notification } from '@layouts/types'
import {
  notificationPulse,
  notificationSoundMuted,
  toggleNotificationSound,
} from '@/composables/useRealtimeAdminNotifications'

interface Props {
  notifications: Notification[]
  badgeProps?: object
  location?: any
  // Authoritative server-side unread total. The list is paginated (15/page), so
  // counting unseen items in `notifications` undercounts once there are more.
  unreadCount?: number
}
interface Emit {
  (e: 'read', value: (number | string)[]): void
  (e: 'unread', value: (number | string)[]): void
  (e: 'remove', value: number | string): void
  (e: 'click:notification', value: Notification): void
}

const props = withDefaults(defineProps<Props>(), {
  location: 'bottom end',
  badgeProps: undefined,
  unreadCount: undefined,
})

const emit = defineEmits<Emit>()

const isAllMarkRead = computed(() => {
  return props.notifications.some(item => item.isSeen === false)
})

const markAllReadOrUnread = () => {
  const allNotificationsIds = props.notifications.map(item => item.id)

  if (!isAllMarkRead.value)
    emit('unread', allNotificationsIds)
  else
    emit('read', allNotificationsIds)
}

const totalUnseenNotifications = computed(() => {
  return props.unreadCount ?? props.notifications.filter(item => item.isSeen === false).length
})

// يهتز الجرس لحظة وصول إشعار فوري جديد
const ringing = ref(false)
watch(notificationPulse, () => {
  ringing.value = false
  requestAnimationFrame(() => { ringing.value = true })
  setTimeout(() => { ringing.value = false }, 1600)
})

const accentColor = (n: Notification) =>
  n.color?.startsWith('#') ? n.color : `rgb(var(--v-theme-${n.color || 'primary'}))`

const toggleReadUnread = (isSeen: boolean, Id: number | string) => {
  if (isSeen)
    emit('unread', [Id])
  else
    emit('read', [Id])
}
</script>

<template>
  <IconBtn
    id="notification-btn"
    :class="{ 'notif-bell--ringing': ringing }"
  >
    <VBadge
      v-bind="props.badgeProps"
      :model-value="totalUnseenNotifications > 0"
      :content="totalUnseenNotifications > 99 ? '99+' : totalUnseenNotifications"
      color="error"
      offset-x="2"
      offset-y="3"
    >
      <VIcon
        class="notif-bell__icon"
        :icon="totalUnseenNotifications > 0 ? 'tabler-bell-ringing' : 'tabler-bell'"
      />
    </VBadge>

    <VMenu
      activator="parent"
      :width="$vuetify.display.smAndDown ? 340 : 400"
      :location="props.location"
      offset="12px"
      :close-on-content-click="false"
    >
      <VCard class="d-flex flex-column notif-card">
        <!-- 👉 Header -->
        <VCardItem class="notification-section">
          <VCardTitle class="text-h6 font-weight-bold">
            {{ $t('notifications.title') }}
          </VCardTitle>

          <template #append>
            <VChip
              v-show="totalUnseenNotifications > 0"
              size="small"
              color="error"
              variant="tonal"
              class="me-1"
            >
              {{ totalUnseenNotifications }} جديد
            </VChip>

            <IconBtn
              size="34"
              @click="toggleNotificationSound"
            >
              <VIcon
                size="20"
                :color="notificationSoundMuted ? 'disabled' : 'high-emphasis'"
                :icon="notificationSoundMuted ? 'tabler-volume-off' : 'tabler-volume'"
              />
              <VTooltip
                activator="parent"
                location="bottom"
              >
                {{ notificationSoundMuted ? 'تشغيل صوت الإشعارات' : 'كتم صوت الإشعارات' }}
              </VTooltip>
            </IconBtn>

            <IconBtn
              v-show="props.notifications.length"
              size="34"
              @click="markAllReadOrUnread"
            >
              <VIcon
                size="20"
                color="high-emphasis"
                :icon="!isAllMarkRead ? 'tabler-mail' : 'tabler-mail-opened' "
              />

              <VTooltip
                activator="parent"
                location="bottom"
              >
                {{ !isAllMarkRead ? 'تعليم الكل كغير مقروء' : 'تعليم الكل كمقروء' }}
              </VTooltip>
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <!-- 👉 Notifications list -->
        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          style="max-block-size: 26rem;"
        >
          <VList class="notification-list rounded-0 py-0">
            <template
              v-for="(notification, index) in props.notifications"
              :key="notification.id"
            >
              <VDivider v-if="index > 0" />
              <VListItem
                link
                lines="one"
                min-height="66px"
                class="list-item-hover-class notif-item"
                :class="{
                  'notif-item--unread': !notification.isSeen,
                  'notif-item--financial': notification.financial,
                }"
                :style="{ '--notif-accent': accentColor(notification) }"
                @click="$emit('click:notification', notification)"
              >
                <div class="d-flex align-start gap-3">
                  <VAvatar
                    :color="notification.color && !notification.img ? notification.color : undefined"
                    :variant="notification.img ? undefined : 'tonal' "
                    rounded="lg"
                    size="40"
                  >
                    <span v-if="notification.text">{{ avatarText(notification.text) }}</span>
                    <VImg
                      v-if="notification.img"
                      :src="notification.img"
                    />
                    <VIcon
                      v-if="notification.icon"
                      :icon="notification.icon"
                      size="22"
                    />
                  </VAvatar>

                  <div class="flex-grow-1" style="min-inline-size: 0;">
                    <div class="d-flex align-center gap-2 mb-1">
                      <span
                        v-if="notification.financial"
                        class="notif-tag"
                      >مالي</span>
                      <p
                        class="text-sm mb-0"
                        :class="notification.isSeen ? 'font-weight-medium' : 'font-weight-bold'"
                      >
                        {{ notification.title }}
                      </p>
                    </div>
                    <p
                      class="text-body-2 mb-1 notif-item__text"
                    >
                      {{ notification.subtitle }}
                    </p>
                    <p class="text-xs text-disabled mb-0">
                      {{ notification.time }}
                    </p>
                  </div>

                  <div class="d-flex flex-column align-center gap-2">
                    <VIcon
                      size="10"
                      icon="tabler-circle-filled"
                      :color="!notification.isSeen ? 'error' : '#a8aaae'"
                      :class="`${notification.isSeen ? 'visible-in-hover' : ''}`"
                      @click.stop="toggleReadUnread(notification.isSeen, notification.id)"
                    />

                    <VIcon
                      size="18"
                      icon="tabler-x"
                      class="visible-in-hover"
                      @click.stop="$emit('remove', notification.id)"
                    />
                  </div>
                </div>
              </VListItem>
            </template>

            <VListItem
              v-show="!props.notifications.length"
              class="text-center text-medium-emphasis py-8"
            >
              <VIcon
                icon="tabler-bell-off"
                size="36"
                class="mb-2 opacity-50"
              />
              <VListItemTitle>لا توجد إشعارات</VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>

        <VDivider />

        <!-- 👉 Footer -->
        <VCardText
          v-show="props.notifications.length"
          class="pa-3"
        >
          <VBtn
            block
            size="small"
            variant="tonal"
            to="/notifications"
          >
            {{ $t('notifications.view_all') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.notification-section {
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.list-item-hover-class {
  .visible-in-hover {
    display: none;
  }

  &:hover {
    .visible-in-hover {
      display: block;
    }
  }
}

.notification-list.v-list {
  .v-list-item {
    border-radius: 0 !important;
    margin: 0 !important;
    padding-block: 0.75rem !important;
  }
}

// عنصر الإشعار: غير المقروء بخلفية خفيفة، والمالي بشريط ذهبي على طرفه
.notif-item {
  position: relative;

  &::before {
    position: absolute;
    background: transparent;
    content: "";
    inline-size: 4px;
    inset-block: 0;
    inset-inline-start: 0;
  }
}

.notif-item--unread {
  background: rgba(var(--v-theme-primary), 0.04);

  &::before {
    background: var(--notif-accent);
  }
}

.notif-item--financial {
  background: rgba(246, 196, 83, 0.1);

  &::before {
    background: var(--notif-accent);
  }

  &.notif-item--unread {
    background: rgba(246, 196, 83, 0.2);
  }
}

.notif-item__text {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  letter-spacing: 0.2px !important;
  line-height: 1.5;
}

.notif-tag {
  flex-shrink: 0;
  padding: 0 8px;
  border-radius: 999px;
  background: var(--notif-accent);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.6;
}

.notif-bell--ringing .notif-bell__icon {
  animation: notif-bell-ring 0.8s ease-in-out 2;
  transform-origin: 50% 10%;
}

@keyframes notif-bell-ring {
  0%, 100% { transform: rotate(0); }
  15% { transform: rotate(18deg); }
  30% { transform: rotate(-16deg); }
  45% { transform: rotate(12deg); }
  60% { transform: rotate(-8deg); }
  75% { transform: rotate(4deg); }
}

@media (prefers-reduced-motion: reduce) {
  .notif-bell--ringing .notif-bell__icon {
    animation: none;
  }
}

// Badge Style Override for Notification Badge
.notification-badge {
  .v-badge__badge {
    /* stylelint-disable-next-line liberty/use-logical-spec */
    min-width: 18px;
    padding: 0;
    block-size: 18px;
  }
}
</style>
