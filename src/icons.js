// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// Icon registry for planninq (ADR-077 semantic icon vocabulary).
//
// CnAppNav, CnIcon and the CnIndexPage / CnDetailPage headers and empty states
// resolve an `icon` by PascalCase name through the registry `registerIcons()`
// populates. An unregistered name renders NO icon — not a fallback glyph — so
// this file must cover every `icon` the manifests and register files name.
// Keep it in sync when a menu entry or schema icon is added.
//
// Every name below is verified to exist in vue-material-design-icons 5.3.x.
// Three of these previously sat in lib/Settings/planninq_register.json as emoji
// ("🌐", "☁️", "🤝"), which resolve to nothing at all: the registry looks up a
// component by name, so an emoji is simply a name that is not registered.

import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import AccountMultiplePlus from 'vue-material-design-icons/AccountMultiplePlus.vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import BriefcaseOutline from 'vue-material-design-icons/BriefcaseOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import ChartAreaspline from 'vue-material-design-icons/ChartAreaspline.vue'
import ChartBar from 'vue-material-design-icons/ChartBar.vue'
import ChartBoxOutline from 'vue-material-design-icons/ChartBoxOutline.vue'
import ChartTimeline from 'vue-material-design-icons/ChartTimeline.vue'
import CheckboxMarkedCircleOutline from 'vue-material-design-icons/CheckboxMarkedCircleOutline.vue'
import ClipboardCheckOutline from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CloudUpload from 'vue-material-design-icons/CloudUpload.vue'
import CurrencyEur from 'vue-material-design-icons/CurrencyEur.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import FolderMultipleOutline from 'vue-material-design-icons/FolderMultipleOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import History from 'vue-material-design-icons/History.vue'
import Home from 'vue-material-design-icons/Home.vue'
import MapMarkerPath from 'vue-material-design-icons/MapMarkerPath.vue'
import NotebookOutline from 'vue-material-design-icons/NotebookOutline.vue'
import SitemapOutline from 'vue-material-design-icons/SitemapOutline.vue'
import SourceBranch from 'vue-material-design-icons/SourceBranch.vue'
import StoreOutline from 'vue-material-design-icons/StoreOutline.vue'
import TagOutline from 'vue-material-design-icons/TagOutline.vue'
import TextBoxOutline from 'vue-material-design-icons/TextBoxOutline.vue'
import TimelineOutline from 'vue-material-design-icons/TimelineOutline.vue'
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import VectorPolyline from 'vue-material-design-icons/VectorPolyline.vue'
import ViewColumnOutline from 'vue-material-design-icons/ViewColumnOutline.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import Web from 'vue-material-design-icons/Web.vue'

export default {
	AccountGroup,
	AccountMultiplePlus,
	AlertOutline,
	BookOpenVariantOutline,
	BriefcaseOutline,
	CalendarClock,
	ChartAreaspline,
	ChartBar,
	ChartBoxOutline,
	ChartTimeline,
	CheckboxMarkedCircleOutline,
	ClipboardCheckOutline,
	ClockOutline,
	CloudUpload,
	CurrencyEur,
	FolderMultipleOutline,
	FolderOutline,
	History,
	Home,
	FilterVariant,
	MapMarkerPath,
	NotebookOutline,
	SitemapOutline,
	SourceBranch,
	StoreOutline,
	TagOutline,
	TextBoxOutline,
	TimelineOutline,
	TimerOutline,
	VectorPolyline,
	ViewColumnOutline,
	ViewDashboardOutline,
	Web,
}
