<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Interfaces\MasjidRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Binding MasjidRepositoryInterface dinamis
        $this->app->bind(\App\Interfaces\MasjidRepositoryInterface::class, function ($app) {
            $masjidName = masjid(); // ambil nama masjid dari helper

            // Default repository namespace jika masjid tidak ada
            $class = "\\App\\Repositories\\{$masjidName}\\MasjidRepository";

            if (!class_exists($class)) {
                throw new \Exception("Repository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding KategoriRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\KategoriRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\KategoriRepository";

            if (!class_exists($class)) {
                throw new \Exception("KategoriRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding BeritaRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\BeritaRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\BeritaRepository";

            if (!class_exists($class)) {
                throw new \Exception("BeritaRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding AcaraRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\AcaraRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\AcaraRepository";

            if (!class_exists($class)) {
                throw new \Exception("AcaraRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding GaleriRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\GaleriRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\GaleriRepository";

            if (!class_exists($class)) {
                throw new \Exception("GaleriRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding PengumumanRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\PengumumanRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\PengumumanRepository";

            if (!class_exists($class)) {
                throw new \Exception("PengumumanRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding ProfilMasjidRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\ProfilMasjidRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\ProfilMasjidRepository";

            if (!class_exists($class)) {
                throw new \Exception("ProfilMasjidRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding KotakInfakRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\KotakInfakRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\KotakInfakRepository";

            if (!class_exists($class)) {
                throw new \Exception("KotakInfakRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding AkunKeuanganRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\AkunKeuanganRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\AkunKeuanganRepository";

            if (!class_exists($class)) {
                throw new \Exception("AkunKeuanganRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding SaldoAwalRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\SaldoAwalRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\SaldoAwalRepository";

            if (!class_exists($class)) {
                throw new \Exception("SaldoAwalRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding JurnalRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\JurnalRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\JurnalRepository";

            if (!class_exists($class)) {
                throw new \Exception("JurnalRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding ZakatRepositoryInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\ZakatRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\ZakatRepository";

            if (!class_exists($class)) {
                throw new \Exception("ZakatRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding DanaTerikatRepositoryInterfaceInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\DanaTerikatRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\DanaTerikatRepository";

            if (!class_exists($class)) {
                throw new \Exception("DanaTerikatRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });

        // Binding DanaTerikatReferensiRepositoryInterfaceInterface dinamis sesuai masjid
        $this->app->bind(\App\Interfaces\DanaTerikatReferensiRepositoryInterface::class, function ($app) {
            $masjidName = masjid();

            $class = "\\App\\Repositories\\{$masjidName}\\DanaTerikatReferensiRepository";

            if (!class_exists($class)) {
                throw new \Exception("DanaTerikatReferensiRepository untuk masjid '{$masjidName}' tidak ditemukan: {$class}");
            }

            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
