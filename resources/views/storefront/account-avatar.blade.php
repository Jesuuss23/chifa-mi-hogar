@if($showCustomerAvatar)
    <img src="{{ route('customer.avatar.show') }}" alt="" width="32" height="32"
         class="h-8 w-8 shrink-0 rounded-full object-cover" decoding="async">
@else
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-none bg-accent text-xs font-black text-primary"
          aria-hidden="true">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
@endif
