export default function ApplicationLogo({
    showWordmark = false,
    markClassName = 'h-9 w-9',
    wordmarkClassName = 'text-lg',
    className = '',
    ...props
}) {
    return (
        <span
            className={`inline-flex items-center gap-2 text-slate-900 dark:text-white ${className}`}
            {...props}
        >
            <svg
                className={markClassName}
                viewBox="0 0 48 48"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <rect
                    x="5"
                    y="7"
                    width="38"
                    height="30"
                    rx="10"
                    className="fill-cyan-400"
                />
                <path
                    d="M15 37L10 43V35"
                    className="fill-cyan-400"
                />
                <path
                    d="M17 18L21.5 29M31 18L26.5 29M19 25H29"
                    className="stroke-slate-950"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <circle cx="35" cy="14" r="4" className="fill-white" />
                <path
                    d="M35 10V18M31 14H39"
                    className="stroke-slate-950"
                    strokeWidth="1.75"
                    strokeLinecap="round"
                />
            </svg>

            {showWordmark && (
                <span
                    className={`font-semibold leading-none tracking-normal ${wordmarkClassName}`}
                >
                    corebot
                </span>
            )}
        </span>
    );
}
