import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import DashboardView from '../views/DashboardView.vue'
import AdminRoot from '../views/settings/AdminRoot.vue'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/planix'),
	routes: [
		{ path: '/', name: 'Dashboard', component: DashboardView },
		{ path: '/my-work', name: 'MyWork', component: () => import('../views/MyWorkView.vue') },
		{ path: '/settings', name: 'Settings', component: AdminRoot },
		{
			path: '/projects',
			name: 'Projects',
			component: () => import('../views/ProjectList.vue'),
		},
		{
			path: '/projects/:id',
			name: 'ProjectBoard',
			component: () => import('../views/ProjectBoard.vue'),
		},
		{
			path: '/projects/:id/backlog',
			name: 'ProjectBacklog',
			component: () => import('../views/ProjectBacklog.vue'),
		},
		{ path: '*', redirect: '/' },
	],
})
