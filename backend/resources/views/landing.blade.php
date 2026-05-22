@extends('layouts.landing')

@section('content')
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 border-b border-border/40 bg-background/80 backdrop-blur-md">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-lg shadow-primary/25">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-primary-foreground">
                            <path d="m22 8-6 4 6 4V8Z" />
                            <rect width="14" height="12" x="2" y="6" rx="2" ry="2" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-foreground flex items-center gap-1.5">
                        {{ config('app.name', 'LiveStream') }}
                        <span class="rounded-md bg-primary/10 px-1.5 py-0.5 text-2xs font-medium text-primary border border-primary/20">AI</span>
                    </span>
                </div>
                
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Tính năng</a>
                    <a href="#how-it-works" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Cách hoạt động</a>
                    <a href="#pricing" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Bảng giá</a>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm shadow-primary/20 hover:bg-primary/95 transition-all hover:scale-[1.02]">
                            Bảng điều khiển
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex h-9 items-center justify-center rounded-xl px-4 text-sm font-semibold text-muted-foreground hover:text-foreground transition-colors">
                            Đăng nhập
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm shadow-primary/20 hover:bg-primary/95 transition-all hover:scale-[1.02]">
                                Trải nghiệm miễn phí
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-36 pb-20 sm:pt-44 sm:pb-28">
        <!-- Mesh Gradients Background -->
        <div class="absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[450px] bg-primary/15 dark:bg-primary/10 opacity-60 rounded-full blur-[120px]"></div>
            <div class="absolute top-40 left-1/3 w-[400px] h-[300px] bg-chart-1/10 dark:bg-chart-1/5 opacity-40 rounded-full blur-[90px]"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(128,128,128,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(128,128,128,0.03)_1px,transparent_1px)] bg-[size:32px_32px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
            <!-- Active Session Badge -->
            <div class="inline-flex items-center gap-2 rounded-full border border-border/80 bg-muted/60 backdrop-blur-md px-4 py-1.5 text-xs font-medium text-muted-foreground mb-8 shadow-xs">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </span>
                Hơn {{ rand(180, 480) }} phiên livestream đang hoạt động trực tuyến
            </div>

            <!-- Main Heading -->
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl max-w-5xl mx-auto leading-[1.1] text-foreground">
                Livestream Bán Hàng.
                <br />
                <span class="bg-gradient-to-r from-primary via-primary/90 to-primary/70 bg-clip-text text-transparent">
                    Chốt Đơn Tự Động Bằng AI.
                </span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-base text-muted-foreground sm:text-lg">
                Tăng tốc doanh số bán hàng livestream của bạn với hệ thống phân tích bình luận thông minh, nhận diện khách hàng tiềm năng và chốt đơn tự động theo thời gian thực.
            </p>

            <!-- Action CTAs -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex h-11 items-center justify-center rounded-xl bg-primary px-8 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.02] hover:-translate-y-0.5">
                    Dùng thử 14 ngày miễn phí
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
                <a href="#how-it-works" class="w-full sm:w-auto inline-flex h-11 items-center justify-center rounded-xl border border-border bg-background px-8 text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                    Tìm hiểu thêm
                </a>
            </div>

            <!-- Custom Styles for Simulation Animation -->
            <style>
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(8px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                .animate-fade-in-up {
                    animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                .scrollbar-none::-webkit-scrollbar {
                    display: none;
                }
                .scrollbar-none {
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }
                .text-2xs {
                    font-size: 0.7rem;
                }
                .text-3xs {
                    font-size: 0.6rem;
                }
                .text-4xs {
                    font-size: 0.55rem;
                }
            </style>

            <!-- 100% Shadcn UI Dashboard Replica Mockup -->
            <div class="relative mt-16 sm:mt-20 mx-auto max-w-6xl rounded-2xl border border-border bg-background shadow-2xl dark:shadow-primary/5 overflow-hidden transition-all duration-300">
                <!-- Mac-style Window Topbar -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-border/40 bg-muted/40">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500/80"></span>
                        <span class="w-3 h-3 rounded-full bg-green-500/80"></span>
                    </div>
                    <div class="text-xs font-semibold text-muted-foreground flex items-center gap-1.5 bg-background border border-border px-3 py-0.5 rounded-md">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-500"></span>
                        </span>
                        Hệ thống chốt đơn tự động đang hoạt động
                    </div>
                    <div class="text-xs text-muted-foreground font-mono">dashboard.livestream.ai</div>
                </div>

                <div class="flex min-h-[640px] bg-background text-foreground text-left">
                    <!-- Left Sidebar (shadcn sidebar component mock) -->
                    <aside class="hidden md:flex flex-col w-56 border-r border-border bg-card/40 p-4 shrink-0">
                        <div class="flex items-center gap-2 px-2 py-1.5 mb-6">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-primary-foreground">
                                    <path d="m22 8-6 4 6 4V8Z" />
                                    <rect width="14" height="12" x="2" y="6" rx="2" ry="2" />
                                </svg>
                            </div>
                            <span class="text-sm font-bold tracking-tight text-foreground">LiveStream AI</span>
                        </div>
                        
                        <nav class="space-y-1">
                            <a href="#" class="flex items-center gap-3 px-3 py-2 text-xs font-semibold rounded-lg bg-primary text-primary-foreground">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                Tổng quan
                            </a>
                            <a href="#" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                                Phiên Live
                            </a>
                            <a href="#" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0 1 12 21c-2.83 0-5.395-.94-7.433-2.522v-.109A11.386 11.386 0 0 1 12 18c1.332 0 2.603.228 3.786.645m-2.256-8.395a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0M9.75 12h4.5" />
                                </svg>
                                Khách hàng
                            </a>
                            <a href="#" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 12 3.75 7.5M12 12v9m-8.25-12 8.25 4.5 8.25-4.5M3 5.25h18v13.5H3V5.25Z" />
                                </svg>
                                Sản phẩm
                            </a>
                            <a href="#" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                                </svg>
                                Báo cáo
                            </a>
                            <a href="#" class="flex items-center gap-3 px-3 py-2 text-xs font-medium rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.645-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                Cài đặt
                            </a>
                        </nav>
                    </aside>

                    <!-- Main Dashboard Content (shadcn page content mock) -->
                    <main class="flex-1 flex flex-col min-w-0 bg-background">
                        <!-- Top Header bar -->
                        <header class="flex h-12 shrink-0 items-center justify-between border-b border-border/40 px-4 bg-background/95 backdrop-blur-md">
                            <div class="flex items-center gap-2">
                                <span class="p-1 rounded hover:bg-muted text-muted-foreground">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                    </svg>
                                </span>
                                <div class="h-4 w-[1px] bg-border/50 mx-1"></div>
                                <div class="text-xs text-muted-foreground flex items-center gap-1.5 font-medium">
                                    <span>Tổng quan</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-800 border border-border flex items-center justify-center text-xs">
                                    👤
                                </div>
                            </div>
                        </header>

                        <!-- Dashboard Workspace Area -->
                        <div class="flex-1 p-4 space-y-4 overflow-y-auto max-h-[600px] scrollbar-none">
                            <!-- Page Action Header -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <h1 class="text-lg font-bold tracking-tight">Tổng quan</h1>
                                    <p class="text-xs text-muted-foreground">Tổng quan hoạt động livestream của bạn</p>
                                </div>
                                <a href="{{ route('register') }}" class="inline-flex h-8 items-center justify-center rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground shadow-sm hover:bg-primary/95 transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="mr-1.5 w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Tạo phân tích phiên live
                                </a>
                            </div>

                            <!-- KPI Cards Row -->
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
                                <!-- KPI 1 -->
                                <div class="rounded-xl border border-border bg-card p-4 shadow-2xs">
                                    <div class="flex flex-row items-center justify-between pb-1.5">
                                        <span class="text-2xs font-medium text-muted-foreground">Tổng phiên Live</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-muted-foreground">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                    </div>
                                    <div class="text-xl font-bold">24</div>
                                    <p class="flex items-center gap-1 text-[10px] text-green-500 font-medium mt-0.5">
                                        🚀 +3 tuần này
                                    </p>
                                </div>
                                <!-- KPI 2 -->
                                <div class="rounded-xl border border-border bg-card p-4 shadow-2xs">
                                    <div class="flex flex-row items-center justify-between pb-1.5">
                                        <span class="text-2xs font-medium text-muted-foreground">Tổng bình luận</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-muted-foreground">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                        </svg>
                                    </div>
                                    <div id="mock-comments-kpi" class="text-xl font-bold">12,847</div>
                                    <p class="flex items-center gap-1 text-[10px] text-green-500 font-medium mt-0.5">
                                        📈 +18% so với tuần trước
                                    </p>
                                </div>
                                <!-- KPI 3 -->
                                <div class="rounded-xl border border-border bg-card p-4 shadow-2xs">
                                    <div class="flex flex-row items-center justify-between pb-1.5">
                                        <span class="text-2xs font-medium text-muted-foreground">Tổng lượt xem</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-muted-foreground">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </div>
                                    <div id="mock-views-kpi" class="text-xl font-bold">89,234</div>
                                    <p class="flex items-center gap-1 text-[10px] text-red-500 font-medium mt-0.5">
                                        📉 -5% so với tuần trước
                                    </p>
                                </div>
                                <!-- KPI 4 -->
                                <div class="rounded-xl border border-border bg-card p-4 shadow-2xs">
                                    <div class="flex flex-row items-center justify-between pb-1.5">
                                        <span class="text-2xs font-medium text-muted-foreground">Cảm xúc tích cực</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-muted-foreground">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                                        </svg>
                                    </div>
                                    <div class="text-xl font-bold">78%</div>
                                    <p class="flex items-center gap-1 text-[10px] text-green-500 font-medium mt-0.5">
                                        😊 +2% so với tuần trước
                                    </p>
                                </div>
                            </div>

                            <!-- Live Session Banner (Alert banner) -->
                            <div class="rounded-xl border border-red-500/25 bg-red-500/5 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-600 text-white text-[10px] font-bold tracking-wide">
                                        <span class="relative flex size-1.5">
                                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75"></span>
                                            <span class="relative inline-flex size-1.5 rounded-full bg-white"></span>
                                        </span>
                                        LIVE
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold">Giới thiệu BST mới</p>
                                        <div class="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                                            <span class="flex items-center gap-1">
                                                👁️ <span id="mock-live-views">3,201</span>
                                            </span>
                                            <span class="flex items-center gap-1">
                                                💬 <span id="mock-live-comments">523</span>
                                            </span>
                                            <span>· thời lượng: 45m</span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('register') }}" class="inline-flex h-8 items-center justify-center rounded-lg bg-red-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-red-600/95 transition-colors self-start sm:self-center">
                                    Vào phân tích
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="ml-1.5 w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            </div>

                            <!-- Charts Row Layout -->
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                                <!-- Area Chart (8 cols) -->
                                <div class="lg:col-span-8 rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
                                    <div>
                                        <h3 class="text-xs font-semibold text-foreground flex items-center gap-2">
                                            📊 Xu hướng 7 ngày qua
                                        </h3>
                                        <p class="text-[10px] text-muted-foreground">Lượt xem và bình luận theo ngày</p>
                                    </div>
                                    
                                    <!-- Double Area Chart Simulation (SVG with grids) -->
                                    <div class="h-[180px] mt-4 flex items-end relative">
                                        <svg class="w-full h-full" viewBox="0 0 500 150" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="views-grad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stop-color="hsl(var(--primary))" stop-opacity="0.25"/>
                                                    <stop offset="95%" stop-color="hsl(var(--primary))" stop-opacity="0.0"/>
                                                </linearGradient>
                                                <linearGradient id="comments-grad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stop-color="#a855f7" stop-opacity="0.25"/>
                                                    <stop offset="95%" stop-color="#a855f7" stop-opacity="0.0"/>
                                                </linearGradient>
                                            </defs>
                                            <!-- Chart grid lines -->
                                            <line x1="0" y1="37" x2="500" y2="37" stroke="var(--border)" stroke-width="0.5" stroke-dasharray="3,3" />
                                            <line x1="0" y1="75" x2="500" y2="75" stroke="var(--border)" stroke-width="0.5" stroke-dasharray="3,3" />
                                            <line x1="0" y1="112" x2="500" y2="112" stroke="var(--border)" stroke-width="0.5" stroke-dasharray="3,3" />
                                            
                                            <!-- Views Area & Line -->
                                            <path d="M 0 130 Q 80 110, 160 80 T 320 50 T 480 30 L 500 35 L 500 150 L 0 150 Z" fill="url(#views-grad)" />
                                            <path d="M 0 130 Q 80 110, 160 80 T 320 50 T 480 30 L 500 35" fill="none" stroke="hsl(var(--primary))" stroke-width="2" />
                                            
                                            <!-- Comments Area & Line -->
                                            <path d="M 0 140 Q 80 125, 160 100 T 320 70 T 480 50 L 500 55 L 500 150 L 0 150 Z" fill="url(#comments-grad)" />
                                            <path d="M 0 140 Q 80 125, 160 100 T 320 70 T 480 50 L 500 55" fill="none" stroke="#a855f7" stroke-width="2" />
                                        </svg>
                                    </div>
                                    <div class="flex justify-between text-[10px] text-muted-foreground mt-2 border-t border-border/40 pt-2 font-mono">
                                        <span>14/05</span>
                                        <span>15/05</span>
                                        <span>16/05</span>
                                        <span>17/05</span>
                                        <span>18/05</span>
                                        <span>19/05</span>
                                        <span>20/05</span>
                                    </div>
                                </div>

                                <!-- Sidebar Content (4 cols) -->
                                <div class="lg:col-span-4 flex flex-col gap-4">
                                    <!-- Hot Keywords Card -->
                                    <div class="rounded-xl border border-border bg-card p-4">
                                        <div>
                                            <h4 class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                                🔍 Từ khóa nổi bật
                                            </h4>
                                            <p class="text-[10px] text-muted-foreground">Khách hàng đang hỏi gì trong live</p>
                                        </div>
                                        <div class="space-y-2 mt-3 text-xs">
                                            <div class="flex items-center justify-between">
                                                <span class="flex items-center gap-2">
                                                    <span class="flex size-4 items-center justify-center rounded-full bg-muted text-[10px] font-bold">1</span>
                                                    <span>"giá bao nhiêu"</span>
                                                </span>
                                                <span class="font-medium text-green-500 flex items-center gap-0.5">487 ▲</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="flex items-center gap-2">
                                                    <span class="flex size-4 items-center justify-center rounded-full bg-muted text-[10px] font-bold">2</span>
                                                    <span>"còn hàng không"</span>
                                                </span>
                                                <span class="font-medium text-green-500 flex items-center gap-0.5">342 ▲</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="flex items-center gap-2">
                                                    <span class="flex size-4 items-center justify-center rounded-full bg-muted text-[10px] font-bold">3</span>
                                                    <span>"ship về HN"</span>
                                                </span>
                                                <span class="font-medium text-red-500 flex items-center gap-0.5">256 ▼</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Top Products Card -->
                                    <div class="rounded-xl border border-border bg-card p-4">
                                        <div>
                                            <h4 class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                                📦 Top sản phẩm
                                            </h4>
                                            <p class="text-[10px] text-muted-foreground">Được nhắc nhiều nhất tuần này</p>
                                        </div>
                                        <div class="space-y-3 mt-3 text-xs">
                                            <div class="space-y-1">
                                                <div class="flex items-center justify-between text-2xs">
                                                    <span class="truncate">Áo thun basic cotton</span>
                                                    <span class="font-medium">342</span>
                                                </div>
                                                <div class="w-full bg-muted rounded-full h-1">
                                                    <div class="bg-primary h-1 rounded-full" style="width: 100%"></div>
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="flex items-center justify-between text-2xs">
                                                    <span class="truncate">Váy hoa mùa hè</span>
                                                    <span class="font-medium">278</span>
                                                </div>
                                                <div class="w-full bg-muted rounded-full h-1">
                                                    <div class="bg-primary h-1 rounded-full" style="width: 81%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Sessions Table -->
                            <div class="rounded-xl border border-border bg-card overflow-hidden">
                                <div class="px-4 py-3 border-b border-border">
                                    <h3 class="text-xs font-semibold text-foreground">Phiên Live gần đây</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-border/80 bg-muted/30">
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground">Tên phiên</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground">Trạng thái</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground text-right">Bình luận</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground text-right">Lượt xem</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground text-right">KH tiềm năng</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground">Cảm xúc</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground">Thời lượng</th>
                                                <th class="p-3 text-2xs font-semibold text-muted-foreground">Ngày</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border/50">
                                            <tr>
                                                <td class="p-3 font-semibold text-foreground">Flash Sale Mùa Hè</td>
                                                <td class="p-3">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-muted-foreground">
                                                        Đã kết thúc
                                                    </span>
                                                </td>
                                                <td class="p-3 text-right">1,247</td>
                                                <td class="p-3 text-right">8,432</td>
                                                <td class="p-3 text-right font-medium text-green-500">45</td>
                                                <td class="p-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-12 bg-muted rounded-full h-1.5">
                                                            <div class="bg-green-500 h-1.5 rounded-full" style="width: 82%"></div>
                                                        </div>
                                                        <span class="text-[10px]">82%</span>
                                                    </div>
                                                </td>
                                                <td class="p-3">2h 15m</td>
                                                <td class="p-3 text-muted-foreground">20/05/2026</td>
                                            </tr>
                                            <tr class="bg-primary/5 border-l-2 border-l-primary">
                                                <td class="p-3 font-semibold text-foreground">Giới thiệu BST mới</td>
                                                <td class="p-3">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-600 dark:bg-red-950/30 dark:text-red-400">
                                                        <span class="w-1 h-1 bg-red-500 rounded-full animate-pulse"></span> Đang Live
                                                    </span>
                                                </td>
                                                <td id="mock-table-comments" class="p-3 text-right">523</td>
                                                <td id="mock-table-views" class="p-3 text-right">3,201</td>
                                                <td id="mock-table-leads" class="p-3 text-right font-medium text-green-500">12</td>
                                                <td class="p-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-12 bg-muted rounded-full h-1.5">
                                                            <div class="bg-yellow-500 h-1.5 rounded-full" style="width: 75%"></div>
                                                        </div>
                                                        <span class="text-[10px]">75%</span>
                                                    </div>
                                                </td>
                                                <td class="p-3">45m</td>
                                                <td class="p-3 text-muted-foreground">20/05/2026</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold text-foreground">Thanh lý cuối tuần</td>
                                                <td class="p-3">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-muted-foreground">
                                                        Đã kết thúc
                                                    </span>
                                                </td>
                                                <td class="p-3 text-right">892</td>
                                                <td class="p-3 text-right">5,678</td>
                                                <td class="p-3 text-right font-medium text-green-500">28</td>
                                                <td class="p-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-12 bg-muted rounded-full h-1.5">
                                                            <div class="bg-red-500 h-1.5 rounded-full" style="width: 68%"></div>
                                                        </div>
                                                        <span class="text-[10px]">68%</span>
                                                    </div>
                                                </td>
                                                <td class="p-3">1h 30m</td>
                                                <td class="p-3 text-muted-foreground">19/05/2026</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>

                <!-- Simulation Action Toast Alert -->
                <div class="absolute bottom-5 right-5 rounded-lg border border-border bg-card p-3 shadow-lg max-w-xs transition-all duration-500 translate-y-20 opacity-0" id="ai-toast">
                    <div class="flex items-center gap-2 text-xs font-semibold text-green-600">
                        <span>✨</span> AI vừa phân tích & chốt đơn thành công!
                    </div>
                    <p class="text-[10px] text-muted-foreground mt-1">Khách hàng <span class="font-semibold text-foreground" id="toast-user">Nguyễn Minh</span> vừa được gửi link thanh toán.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Simulation Script (Executed in global scope) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const commentsKpi = document.getElementById('mock-comments-kpi');
            const viewsKpi = document.getElementById('mock-views-kpi');
            const liveViews = document.getElementById('mock-live-views');
            const liveComments = document.getElementById('mock-live-comments');
            
            const tableComments = document.getElementById('mock-table-comments');
            const tableViews = document.getElementById('mock-table-views');
            const tableLeads = document.getElementById('mock-table-leads');
            
            const aiToast = document.getElementById('ai-toast');
            const toastUser = document.getElementById('toast-user');
            
            let totalComments = 12847;
            let totalViews = 89234;
            
            let activeViews = 3201;
            let activeComments = 523;
            let activeLeads = 12;

            const names = ["Nguyễn Lan", "Lê Minh", "Vũ Hoàng", "Trần Hằng", "Phạm Ngọc", "Đỗ Trang", "Ngô Hạnh"];

            // Update stats dynamically in the mockup to simulate real-time processing
            setInterval(() => {
                // Generate viewer additions
                const addedViews = Math.floor(Math.random() * 5) + 1;
                activeViews += addedViews;
                totalViews += addedViews;
                
                liveViews.innerText = activeViews.toLocaleString('vi-VN');
                tableViews.innerText = activeViews.toLocaleString('vi-VN');
                viewsKpi.innerText = totalViews.toLocaleString('vi-VN');

                // Simulate incoming comments and leads chốt đơn
                if (Math.random() > 0.6) {
                    const addedComments = 1;
                    activeComments += addedComments;
                    totalComments += addedComments;

                    liveComments.innerText = activeComments.toLocaleString('vi-VN');
                    tableComments.innerText = activeComments.toLocaleString('vi-VN');
                    commentsKpi.innerText = totalComments.toLocaleString('vi-VN');

                    // 40% chance the comment is an order
                    if (Math.random() > 0.6) {
                        activeLeads += 1;
                        tableLeads.innerText = activeLeads.toLocaleString('vi-VN');
                        tableLeads.classList.add('scale-125', 'text-green-400');
                        setTimeout(() => tableLeads.classList.remove('scale-125', 'text-green-400'), 300);

                        // Trigger shadcn-like toast notification
                        const randomName = names[Math.floor(Math.random() * names.length)];
                        toastUser.innerText = randomName;
                        aiToast.classList.remove('translate-y-20', 'opacity-0');
                        
                        setTimeout(() => {
                            aiToast.classList.add('translate-y-20', 'opacity-0');
                        }, 3500);
                    }
                }
            }, 3000);
        });
    </script>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 border-t border-border/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">Tính năng được thiết kế cho sự đột phá</h2>
                <p class="mt-4 text-base text-muted-foreground">Tự động hóa toàn bộ quy trình bán hàng livestream để bạn có thể tập trung tương tác tốt nhất với khách hàng.</p>
            </div>
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Phân tích hội thoại tự động</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Công nghệ AI xử lý và phân tích ngữ nghĩa bình luận của khách hàng theo thời gian thực để phân loại nhu cầu chốt đơn.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Chốt đơn tự động</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Tự động nhận diện cú pháp đặt hàng của khách hàng (Mã SP + SĐT) để lên đơn tạm tính và gửi link thanh toán tức thì.</p>
                </div>

                <!-- Feature 3 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Báo cáo hiệu suất Realtime</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Biểu đồ trực quan hóa dữ liệu người xem, số lượng đơn chốt, doanh thu tạm tính và hiệu suất bán hàng liên tục.</p>
                </div>

                <!-- Feature 4 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 12 3.75 7.5M12 12v9m-8.25-12 8.25 4.5 8.25-4.5M3 5.25h18v13.5H3V5.25Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Đồng bộ kho tự động</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Khấu trừ trực tiếp số lượng tồn kho của SKU sản phẩm ngay khi hệ thống chốt đơn thành công để tránh quá tải.</p>
                </div>

                <!-- Feature 5 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94-3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Phân nhóm khách hàng</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Tự động nhận diện khách hàng thân thiết, cảnh báo khách hàng có lịch sử huỷ đơn hoặc bom hàng trực tiếp.</p>
                </div>

                <!-- Feature 6 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9s2.015-9 4-9m0 0a9.003 9.003 0 0 1 8.716 2.253M12 3a9.003 9.003 0 0 0-8.716 2.253M12 12h.008v.008H12V12Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Kết nối đa kênh dễ dàng</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Liên kết nhanh chóng với TikTok Shop, Facebook Page hoặc hệ thống quản lý đơn hàng ngoại vi thông qua API.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-24 bg-muted/40 border-t border-border/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">Quy trình triển khai tinh gọn</h2>
                <p class="mt-4 text-base text-muted-foreground">Chỉ mất chưa đầy 3 phút để hệ thống AI bắt đầu tiếp quản việc chốt đơn của bạn.</p>
            </div>
            
            <div class="relative grid gap-8 md:grid-cols-3">
                <!-- Visual Connection Line -->
                <div class="absolute top-1/2 left-1/6 right-1/6 h-0.5 bg-border/80 -translate-y-8 hidden md:block -z-10"></div>
                
                <!-- Step 1 -->
                <div class="bg-card border border-border p-8 rounded-2xl text-center relative shadow-xs">
                    <div class="mx-auto -mt-14 mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25">
                        01
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Kết nối nguồn Livestream</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Tạo tài khoản và kết nối nền tảng livestream của bạn (ví dụ: TikTok Shop) cực kỳ bảo mật chỉ qua vài cú click chuột.</p>
                </div>

                <!-- Step 2 -->
                <div class="bg-card border border-border p-8 rounded-2xl text-center relative shadow-xs">
                    <div class="mx-auto -mt-14 mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25">
                        02
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Chọn Sản phẩm & Từ khóa AI</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Thêm danh sách hàng hóa cần bán, mã SKU và thiết lập từ khóa kịch bản AI nhận diện cú pháp đặt đơn của khách hàng.</p>
                </div>

                <!-- Step 3 -->
                <div class="bg-card border border-border p-8 rounded-2xl text-center relative shadow-xs">
                    <div class="mx-auto -mt-14 mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25">
                        03
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Bắt đầu Live & Chốt đơn</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Hệ thống tự động phân tích hội thoại, tự lên đơn nháp và tự nhắn tin dẫn đường link thanh toán cho khách mua hàng.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 border-t border-border/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">Bảng giá minh bạch</h2>
                <p class="mt-4 text-base text-muted-foreground">Không có chi phí ẩn. Chọn gói tài khoản phù hợp với quy mô kinh doanh của bạn.</p>
            </div>

            <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-2 lg:max-w-none">
                <!-- Package 1: Basic Trial -->
                <div class="flex flex-col justify-between rounded-3xl border border-border bg-card p-8 shadow-xs">
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Dùng thử miễn phí</h3>
                        <p class="mt-2 text-sm text-muted-foreground">Trải nghiệm hệ thống cơ bản trước khi quyết định đầu tư dài hạn.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-foreground">0đ</span>
                            <span class="text-sm font-medium text-muted-foreground">/ 14 ngày</span>
                        </div>
                        <ul class="mt-8 space-y-4 border-t border-border/60 pt-8 text-sm">
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Giới hạn 3 phiên livestream phân tích</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Phân tích hội thoại thời gian thực</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Quản lý catalog 10 sản phẩm tối đa</span>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route('register') }}" class="w-full mt-8 inline-flex h-10 items-center justify-center rounded-xl border border-border bg-background text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                        Bắt đầu ngay
                    </a>
                </div>

                <!-- Package 2: Pro Plan -->
                <div class="flex flex-col justify-between rounded-3xl border-2 border-primary bg-card p-8 shadow-xl relative">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-primary px-4 py-1 text-2xs font-bold uppercase tracking-wider text-primary-foreground">
                        Khuyên dùng nhiều nhất
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Gói Chuyên Nghiệp (Pro)</h3>
                        <p class="mt-2 text-sm text-muted-foreground">Giải pháp tối ưu cho cá nhân bán hàng và các hộ kinh doanh trực tuyến.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-foreground">299.000đ</span>
                            <span class="text-sm font-medium text-muted-foreground">/ tháng</span>
                        </div>
                        <ul class="mt-8 space-y-4 border-t border-border/60 pt-8 text-sm">
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground font-semibold text-foreground">Giới hạn 30 phiên live / tháng</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Nhận dạng AI chốt đơn thông minh</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Không giới hạn sản phẩm SKU catalog</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Trích xuất báo cáo xuất file Excel/PDF</span>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route('register') }}" class="w-full mt-8 inline-flex h-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-md shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.01]">
                        Đăng ký ngay
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 sm:py-24 border-t border-border/40">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-primary px-8 py-16 text-center shadow-2xl shadow-primary/25 sm:px-16">
                <!-- Decorative background light -->
                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary via-primary/90 to-primary/80"></div>
                <div class="absolute top-0 right-0 -mt-12 -mr-12 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
                
                <h2 class="text-3xl font-extrabold tracking-tight text-primary-foreground sm:text-4xl">
                    Đưa doanh thu Livestream của bạn lên tầm cao mới
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base text-primary-foreground/80">
                    Gia nhập cộng đồng hơn 10.000 chủ shop đã tự động hóa thành công khâu chốt đơn livestream và bùng nổ doanh số.
                </p>
                <div class="mt-10 flex flex-col md:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register') }}" class="w-full md:w-auto inline-flex h-11 items-center justify-center rounded-xl bg-background px-8 text-sm font-bold text-primary shadow-lg hover:bg-muted transition-all hover:scale-[1.02] hover:-translate-y-0.5">
                        Thử nghiệm miễn phí ngay
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-border/40 py-12 bg-muted/20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-primary-foreground">
                            <path d="m22 8-6 4 6 4V8Z" />
                            <rect width="14" height="12" x="2" y="6" rx="2" ry="2" />
                        </svg>
                    </div>
                    <span class="text-sm font-bold tracking-tight text-foreground">{{ config('app.name', 'LiveStream') }} AI</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    &copy; {{ date('Y') }} {{ config('app.name', 'LiveStream') }} AI. Được thiết kế dành cho các nhà bán hàng hiện đại.
                </p>
            </div>
        </div>
    </footer>
@endsection
