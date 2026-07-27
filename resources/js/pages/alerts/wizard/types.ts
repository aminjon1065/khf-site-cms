export type Localized = Record<string, string>;

export interface Reference {
    severities: { value: string; label: string }[];
    hazards: { value: string; label: string; icon: string }[];
    channels: { value: string; label: string }[];
    regions: {
        id: number;
        code: string;
        name: string;
        districts_count: number;
        districts: { id: number; name: string }[];
    }[];
    sources: string[];
    riskCategories: { value: string; label: string }[];
    approvers: { id: number; name: string }[];
    instructions: { id: number; name: string }[];
}
