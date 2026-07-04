export interface User {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}

/** A single field definition from a template schema. */
export interface FieldDef {
    type:
        | 'text' | 'textarea' | 'richtext' | 'number'
        | 'image' | 'gallery' | 'audio'
        | 'color' | 'font' | 'select'
        | 'date' | 'datetime' | 'time'
        | 'boolean' | 'url' | 'map';
    label?: string;
    help?: string;
    required?: boolean;
    max?: number;
    default?: unknown;
    group?: string;
    options?: Array<{ value: string; label: string }>;
}

export interface RepeaterDef {
    label?: string;
    item_label?: string;
    item_fields: Record<string, FieldDef>;
}

export interface SectionDef {
    label: string;
    icon?: string;
    fields?: Record<string, FieldDef>;
    repeater?: RepeaterDef;
}

export interface Template {
    key: string;
    name: string;
    version: string;
    description?: string;
    thumbnail?: string | null;
    status: string;
    sections: Record<string, SectionDef>;
    theme: Record<string, string>;
    theme_schema: Record<string, FieldDef>;
}

export interface MediaImage {
    id: number;
    name: string;
    thumb: string;
    web: string;
    full: string;
    alt: string;
}

export interface MediaAudio {
    id: number;
    name: string;
    url: string;
}

export interface Invitation {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    published_at: string | null;
    view_count: number;
    locale: string;
    template: { key: string; name: string };
    settings: Record<string, any>;
    theme: Record<string, any>;
    seo: Record<string, any>;
    public_url: string;
    media: { images: MediaImage[]; audio: MediaAudio[] };
    counts: { rsvps: number; guestbook: number };
    created_at: string;
    updated_at: string;
}

export interface InvitationListItem {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    published_at: string | null;
    view_count: number;
    public_url: string;
    template: { key: string | null; name: string | null; thumbnail: string | null };
    counts: { rsvps: number; guestbook: number };
    updated_at: string;
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number; per_page: number };
}
