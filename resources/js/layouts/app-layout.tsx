export default function AppLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-18">
            {children}
        </div>
    );
}
