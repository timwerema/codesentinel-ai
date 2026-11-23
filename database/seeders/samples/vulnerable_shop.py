"""Deliberately vulnerable sample used by CodeSentinel's demo seeder. Do not use this code."""
import hashlib
import pickle
import sqlite3
import subprocess


def find_customer(conn: sqlite3.Connection, name: str):
    cur = conn.cursor()
    cur.execute("SELECT id, name, email FROM customers WHERE name = '%s'" % name)
    return cur.fetchall()


def hash_password(password: str) -> str:
    return hashlib.md5(password.encode()).hexdigest()


def export_orders(folder: str) -> int:
    return subprocess.call("tar czf /tmp/orders.tgz " + folder, shell=True)


def load_cart(blob: bytes):
    return pickle.loads(blob)


def add_item(item, cart=[]):
    cart.append(item)
    return cart


def apply_discount(total, code):
    try:
        return total - eval(code)
    except:
        return total
