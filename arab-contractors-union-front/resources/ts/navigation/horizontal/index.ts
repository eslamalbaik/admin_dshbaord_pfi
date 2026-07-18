import type { HorizontalNavItems } from '@layouts/types'

export default [
  {
    title: 'Dashboard',
    icon: { icon: 'tabler-layout-dashboard' },
    to: 'dashboards',
  },
  {
    title: 'Courses',
    icon: { icon: 'tabler-book-2' },
    children: [
      { title: 'All Courses', to: 'courses' },
      { title: 'Create Course', to: 'courses-create' },
      { title: 'Categories', to: 'courses-categories' },
    ],
  },
  {
    title: 'Students',
    icon: { icon: 'tabler-users' },
    children: [
      { title: 'Students List', to: 'students' },
      { title: 'Enrollments', to: 'students-enrollments' },
    ],
  },
  {
    title: 'Quizzes',
    icon: { icon: 'tabler-clipboard-check' },
    children: [
      { title: 'Question Bank', to: 'quizzes-bank' },
      { title: 'Exams', to: 'quizzes-exams' },
      { title: 'Results', to: 'quizzes-results' },
    ],
  },
  {
    title: 'More',
    icon: { icon: 'tabler-dots' },
    children: [
      { title: 'Payments', to: 'payments-transactions', icon: { icon: 'tabler-credit-card' } },
      { title: 'Certificates', to: 'certificates', icon: { icon: 'tabler-certificate' } },
      { title: 'Analytics', to: 'analytics', icon: { icon: 'tabler-chart-bar' } },
      { title: 'Notifications', to: 'notifications', icon: { icon: 'tabler-bell' } },
      { title: 'Media Library', to: 'media', icon: { icon: 'tabler-photo' } },
      { title: 'Settings', to: 'settings', icon: { icon: 'tabler-settings' } },
    ],
  },
] as HorizontalNavItems
