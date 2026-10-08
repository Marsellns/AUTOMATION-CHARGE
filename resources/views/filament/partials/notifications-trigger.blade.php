<x-filament::icon-button
    :badge="$unreadNotificationsCount ?: null"
    color="gray"
    icon="heroicon-o-bell"
    icon-size="lg"
    :label="$unreadNotificationsCount ? 'Notifikasi: '.$unreadNotificationsCount.' belum dibaca' : 'Notifikasi'"
    class="fi-topbar-database-notifications-btn"
/>
