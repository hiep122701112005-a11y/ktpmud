import random


def game_doan_so():
    print("=" * 40)
    print("   CHÀO MỪNG BẠN ĐẾN VỚI GAME ĐOÁN SỐ!")
    print("=" * 40)
    print("Máy tính đã chọn một số bí mật từ 1 đến 100.")

    # Máy tính chọn ngẫu nhiên 1 số từ 1 đến 100
    so_bi_mat = random.randint(1, 100)
    so_lan_doan = 0

    while True:
        try:
            # Nhập số từ người chơi
            doan = int(input("\nNhập số bạn đoán (1-100): "))
            so_lan_doan += 1

            if doan < 1 or doan > 100:
                print("Vui lòng chỉ nhập số trong khoảng từ 1 đến 100!")
                continue

            # Kiểm tra kết quả
            if doan < so_bi_mat:
                print(">> Số bí mật LỚN HƠN số của bạn!")
            elif doan > so_bi_mat:
                print(">> Số bí mật NHỎ HƠN số của bạn!")
            else:
                print(
                    f"\n🎉 CHÚC MỪNG! Bạn đã đoán đúng số {so_bi_mat}!"
                )
                print(f"🏆 Bạn mất tổng cộng {so_lan_doan} lần đoán.")
                break

        except ValueError:
            print("Lỗi: Vui lòng nhập một số nguyên hợp lệ!")


if __name__ == "__main__":
    game_doan_so()
  
