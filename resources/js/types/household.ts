export type HouseholdRef = {
    id: number;
};

export type HouseholdDetails = HouseholdRef & {
    name: string;
    currency: string;
};

export type MemberOption = {
    id: number;
    name: string;
};
