export interface LegalSettings {
    support_email: string;
    operator_name: string | null;
    business_address: string | null;
    registration_number: string | null;
    minimum_age: number | null;
    version: string;
    purchase_consent_text?: string;
    purchase_policy_version?: string;
}
export interface LegalDocument {
    id: 'contact' | 'privacy' | 'terms' | 'refunds';
    title: string;
    summary: string;
    updated_at: string;
    sections: {
        id: string;
        title: string;
        paragraphs: string[];
        bullets?: string[];
        links?: { label: string; href: string }[];
    }[];
}
export interface SupportReceipt {
    reference: string;
    received_at: string;
}
