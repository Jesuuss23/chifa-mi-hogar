@error('google')
    <p role="alert" class="mt-4 border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{{ $message }}</p>
@enderror

@if(\App\Services\GoogleCustomerLogin::enabled())
    <form method="POST" action="{{ route('customer.google.start') }}" class="mt-6">
        @csrf
        <button type="submit" class="auth-social-button auth-social-google">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20" height="20" aria-hidden="true" focusable="false">
                <path fill="#4285F4" d="M43.61 24.46c0-1.36-.12-2.66-.35-3.92H24v7.42h11a9.4 9.4 0 0 1-4.08 6.17v5h6.61c3.87-3.56 6.08-8.8 6.08-14.67Z"/>
                <path fill="#34A853" d="M24 44c5.51 0 10.13-1.83 13.51-4.95l-6.61-5c-1.83 1.23-4.17 1.98-6.9 1.98-5.31 0-9.82-3.59-11.43-8.43H5.75v5.18A20 20 0 0 0 24 44Z"/>
                <path fill="#FBBC05" d="M12.57 27.6a12 12 0 0 1 0-7.2v-5.18H5.75a20 20 0 0 0 0 17.56l6.82-5.18Z"/>
                <path fill="#EA4335" d="M24 11.97c3.01 0 5.69 1.04 7.82 3.08l5.86-5.86A19.6 19.6 0 0 0 24 4a20 20 0 0 0-18.25 11.22l6.82 5.18C14.18 15.56 18.69 11.97 24 11.97Z"/>
            </svg>
            <span>Continuar con Google</span>
        </button>
    </form>
@endif
