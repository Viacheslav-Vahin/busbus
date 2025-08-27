// BusLayout.tsx
import React from "react";

type SeatTypeCode = "classic" | "recliner" | "panoramic" | string;

interface Seat {
    row: string | number;
    column: string | number;
    type: "seat" | "driver" | "wc" | "coffee" | "stuardesa" | "stairs" | "exit" | string;
    price?: string | number;
    number?: string | null;
    seat_type?: SeatTypeCode;
    // для службових елементів (можуть бути)
    w?: string | number;
    h?: string | number;
}

interface BusLayoutProps {
    seatLayout: Seat[];
    bookedSeats: string[];
    selectedSeats: string[];
    onSeatClick: (number: string) => void;
    heldSeats?: string[];
}

const TYPE_EMOJI: Record<string, string> = {
    driver: "🚍",
    wc: "🚻",
    coffee: "☕",
    stuardesa: "🧑‍✈️",
    stairs: "🪜",
    exit: "🚪",
};

const TYPE_BADGE: Record<SeatTypeCode, { label: string; cls: string }> = {
    classic:   { label: "C", cls: "bg-gray-800" },
    recliner:  { label: "R", cls: "bg-teal-700" },
    panoramic: { label: "P", cls: "bg-indigo-700" },
};

export default function BusLayout({
                                      seatLayout,
                                      bookedSeats,
                                      selectedSeats,
                                      onSeatClick,
                                      heldSeats = [],
                                  }: BusLayoutProps) {
    const maxRow = seatLayout.length
        ? Math.max(...seatLayout.map((s) => Number(s.row) || 1))
        : 1;
    const maxCol = seatLayout.length
        ? Math.max(...seatLayout.map((s) => Number(s.column) || 1))
        : 1;

    return (
        <div
            className="seat-layout bus-body"
            style={{
                display: "grid",
                gap: "10px",
                gridTemplateRows: `repeat(${maxRow}, auto)`,
                gridTemplateColumns: `repeat(${maxCol}, 1fr)`,
            }}
        >
            <div className="wheel wheel-left" />
            <div className="wheel wheel-botom-left" />

            {seatLayout.map((seat, idx) => {
                const isSeat = seat.type === "seat";
                const seatNum = seat.number || "";
                const isBooked = isSeat && bookedSeats.includes(seatNum);
                const isHeld   = isSeat && heldSeats.includes(seatNum);
                const isSelected = isSeat && selectedSeats.includes(seatNum);

                // grid span (для службових елементів з w/h)
                const rowSpan = Number(seat.h) || 1;
                const colSpan = Number(seat.w) || 1;

                let className =
                    "relative text-center border rounded-t-lg p-3 select-none transition-colors ";

                if (isSeat) {
                    if (isBooked || isHeld) {
                        className += "bg-gray-300 cursor-not-allowed ";
                    } else if (isSelected) {
                        className += "bg-green-400 text-white ";
                    } else {
                        className += "bg-white hover:bg-green-200 cursor-pointer ";
                    }
                }

                const style: React.CSSProperties = {
                    gridRow: `${seat.row} / span ${rowSpan}`,
                    gridColumn: `${seat.column} / span ${colSpan}`,
                    border: "3px solid #faa51a",
                    borderRadius: "18px 18px 6px 6px",
                };

                const badge =
                    isSeat && seat.seat_type && TYPE_BADGE[seat.seat_type]
                        ? TYPE_BADGE[seat.seat_type]
                        : undefined;

                const content = isSeat ? (
                    <>
                        {/* бейдж типу сидіння */}
                        {badge && (
                            <span
                                className={`absolute -top-1 -right-1 text-[10px] leading-none text-white rounded-full px-1.5 py-0.5 ${badge.cls}`}
                                title={
                                    seat.seat_type === "recliner"
                                        ? "Реклайнер"
                                        : seat.seat_type === "panoramic"
                                            ? "Панорамне"
                                            : "Класичне"
                                }
                            >
                {badge.label}
              </span>
                        )}
                        <span className="block font-medium">{seatNum || "N/A"}</span>
                        {seat.price !== undefined && seat.price !== "" && (
                            <span className="block text-xs opacity-80">{seat.price} грн</span>
                        )}
                    </>
                ) : (
                    <span className="text-base" title={seat.type.toUpperCase()}>
            {TYPE_EMOJI[seat.type] ?? seat.type.toUpperCase()}
          </span>
                );

                return (
                    <div
                        key={`${seat.type}-${seatNum || idx}`}
                        className={className}
                        style={style}
                        aria-disabled={isSeat && (isBooked || isHeld) ? true : undefined}
                        onClick={() => {
                            if (!isSeat || !seatNum) return;
                            if (isBooked || isHeld) return;
                            onSeatClick(seatNum);
                        }}
                    >
                        {content}
                    </div>
                );
            })}

            <div className="wheel wheel-right" />
            <div className="wheel wheel-botom-right" />
        </div>
    );
}
