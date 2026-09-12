export interface Purchase {
    id: string;
    bundle_id: string;
    name: string;
    status: string;
    amount: number;
    currency: string;
    total_amount: number | null;
    paid_at: string | null;
    created_at: string;
    cosmetics: { category: string; id: string; name: string }[];
    first_used_at: string | null;
    games_used: number;
    refund: {
        can_request: boolean;
        status: string;
        label: string;
        explanation: string;
        deadline: string | null;
    };
    receipt_sent_at: string | null;
    withdrawal_url: string;
}
export interface PurchasePage {
    data: Purchase[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
}
