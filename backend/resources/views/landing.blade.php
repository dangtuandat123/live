@extends('layouts.landing')

@section('content')
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 border-b border-border/40 bg-background/80 backdrop-blur-xl">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary">
                        <svg class="h-4 w-4 text-primary-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold">{{ config('app.name', 'LiveStream') }}</span>
                </div>
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Tính năng</a>
                    <a href="#how-it-works" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Cách hoạt động</a>
                    <a href="#pricing" class="text-sm text-muted-foreground hover:text-foreground transition-colors">Bảng giá</a>
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 transition-colors">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                            Đăng nhập
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 transition-colors">
                                Dùng thử miễn phí
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
        <div class="absolute inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-primary/5"></div>
            <div class="absolute top-1/4 left-1/4 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
        </div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-4 py-1.5 text-sm text-muted-foreground mb-8">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </span>
                Đang có {{ rand(120, 500) }} phiên live đang diễn ra
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl">
                Livestream Bán Hàng.
                <br />
                <span class="bg-gradient-to-r from-primary via-primary/80 to-primary/60 bg-clip-text text-transparent">
                    Chốt Đơn Tự Động.
                </span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground sm:text-xl">
                Nền tảng livestream bán hàng thông minh giúp bạn tăng doanh thu <strong class="text-foreground">300%</strong>
                với hệ thống chốt đơn tự động, quản lý kho hàng realtime và phân tích dữ liệu AI.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground shadow-lg hover:bg-primary/90 transition-all hover:shadow-xl hover:-translate-y-0.5">
                    Bắt đầu miễn phí
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
                <a href="#how-it-works" class="inline-flex items-center justify-center rounded-md border border-border bg-background px-8 py-3 text-base font-medium text-foreground hover:bg-muted transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Xem Demo
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Tất cả trong một nền tảng</h2>
                <p class="mt-4 text-lg text-muted-foreground">Mọi công cụ bạn cần để livestream bán hàng chuyên nghiệp</p>
            </div>
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1 -->
                <div class="group relative rounded-xl border border-border bg-card p-8 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">Livestream HD</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Phát trực tiếp chất lượng cao trên nhiều nền tảng cùng lúc (Facebook, TikTok, YouTube).</p>
                </div>
                <!-- Feature 2 -->
                <div class="group relative rounded-xl border border-border bg-card p-8 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">Chốt Đơn Tự Động</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Khách bình luận = Tự động tạo đơn. Không bỏ sót bất kỳ đơn hàng nào trong lúc live.</p>
                </div>
                <!-- Feature 3 -->
                <div class="group relative rounded-xl border border-border bg-card p-8 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">Phân Tích AI</h3>
                    <p class="mt-2 text-sm text-muted-foreground">AI phân tích hành vi khách hàng, gợi ý sản phẩm bán chạy và thời điểm vàng để live.</p>
                </div>
                <!-- Feature 4 -->
                <div class="group relative rounded-xl border border-border bg-card p-8 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">Quản Lý Kho</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Đồng bộ tồn kho realtime. Tự động trừ kho khi chốt đơn, cảnh báo hết hàng ngay trên màn hình live.</p>
                </div>
                <!-- Feature 5 -->
                <div class="group relative rounded-xl border border-border bg-card p-8 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">CRM Khách Hàng</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Lưu trữ thông tin khách, lịch sử mua hàng. Gửi tin nhắn chăm sóc tự động sau live.</p>
                </div>
                <!-- Feature 6 -->
                <div class="group relative rounded-xl border border-border bg-card p-8 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold">Thanh Toán Đa Kênh</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Tích hợp COD, chuyển khoản, ví điện tử. Gửi link thanh toán tự động cho khách ngay trong live.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-20 sm:py-28 bg-muted/30">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Đơn giản chỉ 3 bước</h2>
                <p class="mt-4 text-lg text-muted-foreground">Bắt đầu bán hàng qua livestream chưa bao giờ dễ đến thế</p>
            </div>
            <div class="grid gap-8 md:grid-cols-3">
                <div class="text-center">
                    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-2xl font-bold text-primary-foreground">1</div>
                    <h3 class="text-lg font-semibold">Tạo tài khoản</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Đăng ký miễn phí trong 30 giây. Kết nối trang bán hàng của bạn.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-2xl font-bold text-primary-foreground">2</div>
                    <h3 class="text-lg font-semibold">Thêm sản phẩm</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Import danh sách sản phẩm, đặt giá, thiết lập mã chốt đơn.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-2xl font-bold text-primary-foreground">3</div>
                    <h3 class="text-lg font-semibold">Bắt đầu Live</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Nhấn nút Live và để hệ thống tự động chốt đơn cho bạn!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                Sẵn sàng tăng doanh thu
                <span class="bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">gấp 3 lần?</span>
            </h2>
            <p class="mt-4 text-lg text-muted-foreground">
                Hơn 10,000+ shop đã tin dùng. Đăng ký ngay hôm nay và nhận 14 ngày dùng thử miễn phí.
            </p>
            <div class="mt-8">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground shadow-lg hover:bg-primary/90 transition-all hover:shadow-xl hover:-translate-y-0.5">
                    Dùng thử miễn phí 14 ngày
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-border py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded bg-primary">
                        <svg class="h-3 w-3 text-primary-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <span class="text-sm font-semibold">{{ config('app.name', 'LiveStream') }}</span>
                </div>
                <p class="text-sm text-muted-foreground">© {{ date('Y') }} {{ config('app.name', 'LiveStream') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
@endsection
