// BusLayout.tsx

import React from "react";

interface Seat {
    row: string;
    column: string;
    type: string;
    price?: string;
    number?: string | null;
}

interface BusLayoutProps {
    seatLayout: Seat[];
    bookedSeats: string[];
    selectedSeats: string[];
    onSeatClick: (number: string) => void;
}

export default function BusLayout({ seatLayout, bookedSeats, selectedSeats, onSeatClick }: BusLayoutProps) {
    const maxRow = Math.max(...seatLayout.map(seat => Number(seat.row)));
    const maxCol = Math.max(...seatLayout.map(seat => Number(seat.column)));

    return (
        <div
            className="seat-layout bus-body"
            style={{
                display: "grid",
                gap: "10px",
                gridTemplateRows: `repeat(${maxRow}, auto)`,
                gridTemplateColumns: `repeat(${maxCol}, 1fr)`
            }}
        >
            <div className="wheel wheel-left"></div>
            <div className="wheel wheel-botom-left"></div>
            {seatLayout.map((seat, idx) => {
                let content;
                if (seat.type === "driver") content = <span>Водій</span>;
                else if (seat.type === "wc") content = <span>WC</span>;
                else if (seat.type === "coffee") content = <span>Кава</span>;
                else if (seat.type === "stuardesa") content = <span>Стюардеса</span>;
                else
                    content = (
                        <>
                            <span className="font-medium block">{seat.number}</span>
                            <span className="text-xs block">{seat.price} грн</span>
                        </>
                    );

                let className = "relative p-3 text-center border rounded-t-lg ";
                if (seat.type === "seat") {
                    if (bookedSeats.includes(seat.number || "")) className += "bg-gray-300 cursor-not-allowed ";
                    else if (selectedSeats.includes(seat.number || "")) className += "bg-green-400 text-white ";
                    else className += "bg-white hover:bg-green-200 cursor-pointer ";
                } else if (seat.type === "driver") className += "bg-orange-100 ";
                else if (seat.type === "wc") className += "bg-orange-100 ";
                else if (seat.type === "coffee") className += "bg-orange-100 ";
                else if (seat.type === "stuardesa") className += "bg-orange-100 ";

                return (
                    <div
                        key={idx}
                        className={className}
                        style={{
                            gridRow: seat.row,
                            gridColumn: seat.column,
                            border: "3px solid #faa51a",
                            borderRadius: "18px 18px 6px 6px"
                        }}
                        onClick={() => seat.type === "seat" && seat.number && !bookedSeats.includes(seat.number) && onSeatClick(seat.number)}
                    >
                        {content}
                    </div>
                );
            })}
            <div className="wheel wheel-right"></div>
            <div className="wheel wheel-botom-right"></div>
        </div>
    );
}
