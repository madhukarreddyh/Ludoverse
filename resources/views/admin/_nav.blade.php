{{-- Shared admin section nav (Phase 6). --}}
<div class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 py-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
        <a href="{{ route('hmkr.dashboard') }}" class="font-semibold {{ request()->routeIs('hmkr.dashboard') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">Dashboard</a>
        <a href="{{ route('hmkr.users.index') }}" class="font-semibold {{ request()->routeIs('hmkr.users.*') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">Users</a>
        <a href="{{ route('hmkr.devices.index') }}" class="font-semibold {{ request()->routeIs('hmkr.devices.*') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">Devices</a>
        <a href="{{ route('hmkr.fraud.index') }}" class="font-semibold {{ request()->routeIs('hmkr.fraud.*') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">Fraud</a>
        <a href="{{ route('hmkr.tables.edit') }}" class="font-semibold {{ request()->routeIs('hmkr.tables.*') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">Tables</a>
        <a href="{{ route('hmkr.api-keys.index') }}" class="font-semibold {{ request()->routeIs('hmkr.api-keys.*') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">API Keys</a>
        <a href="{{ route('hmkr.support.index') }}" class="font-semibold {{ request()->routeIs('hmkr.support.*') ? 'text-indigo-700' : 'text-slate-600 hover:text-indigo-700' }}">Support</a>
        <a href="{{ route('hmkr.deposits.index') }}" class="font-semibold text-slate-600 hover:text-indigo-700">Deposits</a>
        <a href="{{ route('hmkr.withdrawals.index') }}" class="font-semibold text-slate-600 hover:text-indigo-700">Withdrawals</a>
        <a href="{{ route('hmkr.tournaments.index') }}" class="font-semibold text-slate-600 hover:text-indigo-700">Tournaments</a>
        <a href="{{ route('hmkr.settings.edit') }}" class="font-semibold text-slate-600 hover:text-indigo-700">Settings</a>
    </div>
</div>
