<footer class="border-t border-border bg-card py-6 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-4">
        <p class="text-sm text-muted-foreground">
            &copy; {{ date('Y') }} <span class="font-medium text-foreground">{{ config('app.name') }}</span>. All rights reserved.
        </p>
        <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-sm text-muted-foreground">
            <a href="{{ route('privacy-policy') }}" class="hover:text-foreground hover:underline transition-colors">Privacy Policy</a>
            <span>•</span>
            <a href="{{ route('terms-and-conditions') }}" class="hover:text-foreground hover:underline transition-colors">Terms &amp; Conditions</a>
            <span>•</span>
            <span>v1.0.0</span>
        </div>
    </div>
</footer>
