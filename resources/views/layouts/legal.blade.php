@props(['title' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ !empty($title) ? $title . ' - ' : '' }}{{ config('app.name', 'Glam & Glow') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @media print {
                header, nav, .no-print, footer .no-print-footer, .print-hidden {
                    display: none !important;
                }
                body {
                    background: #ffffff !important;
                    color: #000000 !important;
                    font-size: 11pt !important;
                }
                .legal-card {
                    box-shadow: none !important;
                    border: none !important;
                    padding: 0 !important;
                }
                .page-break {
                    page-break-before: always;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-slate-50 text-foreground min-h-screen flex flex-col selection:bg-primary selection:text-primary-foreground">
        <!-- Top Navigation Bar -->
        <header class="sticky top-0 z-40 w-full border-b border-border bg-white/95 backdrop-blur-md transition-all shadow-xs">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <!-- Brand / Logo -->
                <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                    <x-application-logo class="w-8 h-8 fill-current text-foreground group-hover:scale-105 transition-transform" />
                    <div>
                        <div class="text-base font-bold tracking-tight text-foreground leading-tight flex items-center gap-1.5">
                            <span>{{ config('app.name', 'Glam & Glow') }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Official</span>
                        </div>
                        <p class="text-[11px] text-muted-foreground leading-none mt-0.5">Cosmetics &amp; Beauty Parlour • Baranagar, Kolkata</p>
                    </div>
                </a>

                <!-- Policy Switcher Tabs -->
                <nav class="hidden sm:flex items-center gap-1 bg-slate-100 p-1 rounded-lg border border-slate-200/80 text-xs font-semibold">
                    <a
                        href="{{ route('privacy-policy') }}"
                        class="px-3 py-1.5 rounded-md transition-all {{ request()->routeIs('privacy-policy') ? 'bg-white text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground' }}"
                    >
                        Privacy Policy
                    </a>
                    <a
                        href="{{ route('terms-and-conditions') }}"
                        class="px-3 py-1.5 rounded-md transition-all {{ request()->routeIs('terms-and-conditions') ? 'bg-white text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground' }}"
                    >
                        Terms &amp; Conditions
                    </a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick="window.print()"
                        title="Print or Save PDF"
                        class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 transition-colors shadow-xs"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print</span>
                    </button>

                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-slate-900 rounded-md hover:bg-slate-800 transition-colors shadow-xs"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 transition-colors shadow-xs"
                        >
                            <span>Staff Login</span>
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Mobile sub-bar for policy switcher -->
            <div class="sm:hidden border-t border-slate-200/80 px-4 py-2 bg-slate-50/80 flex items-center justify-center gap-2">
                <a
                    href="{{ route('privacy-policy') }}"
                    class="flex-1 text-center py-1.5 rounded-md text-xs font-semibold {{ request()->routeIs('privacy-policy') ? 'bg-white text-slate-900 shadow-xs border border-slate-200' : 'text-slate-600' }}"
                >
                    Privacy Policy
                </a>
                <a
                    href="{{ route('terms-and-conditions') }}"
                    class="flex-1 text-center py-1.5 rounded-md text-xs font-semibold {{ request()->routeIs('terms-and-conditions') ? 'bg-white text-slate-900 shadow-xs border border-slate-200' : 'text-slate-600' }}"
                >
                    Terms &amp; Conditions
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 py-8 sm:py-12">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200 bg-white py-10 mt-auto">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pb-6 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <x-application-logo class="w-6 h-6 fill-current text-slate-700" />
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ config('app.name', 'Glam & Glow') }}</p>
                            <p class="text-xs text-slate-500">Baranagar, Kolkata, West Bengal, India</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-medium text-slate-600">
                        <a href="{{ route('privacy-policy') }}" class="hover:text-slate-900 hover:underline">Privacy Policy</a>
                        <span>•</span>
                        <a href="{{ route('terms-and-conditions') }}" class="hover:text-slate-900 hover:underline">Terms &amp; Conditions</a>
                        <span>•</span>
                        <a href="https://wa.me/919051715322" target="_blank" rel="noopener" class="inline-flex items-center gap-1 hover:text-emerald-700">
                            <span>WhatsApp: +91 90517 15322</span>
                        </a>
                    </div>
                </div>
                <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <p>&copy; {{ date('Y') }} {{ config('app.name', 'Glam & Glow') }}. All rights reserved.</p>
                    <p class="italic text-slate-400">Let's Make Baranagar Beautiful!</p>
                </div>
            </div>
        </footer>
    </body>
</html>
