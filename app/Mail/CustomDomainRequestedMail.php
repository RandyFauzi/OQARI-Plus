<?php

namespace App\Mail;

use App\Models\ShopDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomDomainRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShopDomain $shopDomain)
    {
        $this->shopDomain->loadMissing('shop');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[OQARI Plus] Pengajuan Domain Kustom: {$this->shopDomain->domain}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $shopName = e($this->shopDomain->shop?->name ?? 'Toko');
        $domain = e($this->shopDomain->domain);
        $target = e($this->shopDomain->cnameTarget());
        $originIp = e((string) config('custom_domains.origin_ip', '46.202.186.252'));
        $dashboardUrl = e(route('superadmin.dashboard'));

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #111827; line-height: 1.6;">
            <h2 style="color: #df5826; margin-bottom: 8px;">Pengajuan Domain Kustom Baru</h2>
            <p>Pemilik toko telah mengajukan dan memverifikasi DNS untuk domain kustom baru di <strong>OQARI Plus</strong>.</p>
            <table style="width: 100%; border-collapse: collapse; margin: 16px 0; background: #f8f9fa; border: 1px solid #e9ebf0;">
                <tr>
                    <td style="padding: 10px 14px; font-weight: bold; border-bottom: 1px solid #e9ebf0;">Nama Toko</td>
                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ebf0;">{$shopName}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 14px; font-weight: bold; border-bottom: 1px solid #e9ebf0;">Domain Diajukan</td>
                    <td style="padding: 10px 14px; border-bottom: 1px solid #e9ebf0;"><code>{$domain}</code></td>
                </tr>
                <tr>
                    <td style="padding: 10px 14px; font-weight: bold;">Target Hostinger</td>
                    <td style="padding: 10px 14px;">Parked Domain pada <code>{$target}</code> (IP: <code>{$originIp}</code>)</td>
                </tr>
            </table>
            <p><strong>Langkah Anda:</strong></p>
            <ol>
                <li>Tambahkan <code>{$domain}</code> sebagai <strong>Parked Domain</strong> pada website <code>oqariplus.appku.site</code> di hPanel Hostinger.</li>
                <li>Buka Dashboard SuperAdmin OQARI Plus lalu klik <strong>Setujui &amp; Aktifkan</strong>.</li>
            </ol>
            <p style="margin-top: 20px;">
                <a href="{$dashboardUrl}" style="display: inline-block; background: #df5826; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: bold;">Buka Dashboard SuperAdmin</a>
            </p>
        </div>
        HTML;
    }
}
