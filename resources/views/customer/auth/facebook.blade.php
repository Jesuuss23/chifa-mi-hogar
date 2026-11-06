@error('facebook')
    <p role="alert" class="mt-4 border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{{ $message }}</p>
@enderror

@if(\App\Services\FacebookCustomerLogin::enabled())
    <form method="POST" action="{{ route('customer.facebook.start') }}" class="mt-3">
        @csrf
        <button type="submit" class="auth-social-button auth-social-facebook">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M13.5 22v-9h3l.5-3h-3.5V8.5c0-.9.3-1.5 1.7-1.5H17V4.3c-.3 0-1.4-.1-2.6-.1-2.6 0-4.4 1.6-4.4 4.5V10H7v3h3v9h3.5Z"/>
            </svg>
            <span>Continuar con Facebook</span>
        </button>
    </form>
@endif
